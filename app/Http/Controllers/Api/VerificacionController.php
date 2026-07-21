<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\RegistroAcceso;
use Illuminate\Http\Request;
class VerificacionController extends Controller
{
    private function sanitizar(string $texto): string
    {
        return mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
    }

    private function cosineDistance($a, $b)
    {
        $dot = 0;
        $normA = 0;
        $normB = 0;
        $n = count($a);
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        $normA = sqrt($normA);
        $normB = sqrt($normB);
        if ($normA == 0 || $normB == 0) return 2;
        return 1 - ($dot / ($normA * $normB));
    }

    private function buscarAlumnoPorEmbedding(array $embedding)
    {
        $alumnos = Alumno::whereNotNull('vector_rostro')
            ->select(['id', 'dni', 'nombre', 'carrera', 'aula_id', 'estado', 'foto_path', 'vector_rostro'])
            ->get();

        $mejor = null;
        $mejorDist = 1.5;

        foreach ($alumnos as $alumno) {
            $clean = str_replace(['{', '}'], ['[', ']'], $alumno->vector_rostro);
            $dbVector = json_decode($clean);
            if (!$dbVector || !is_array($dbVector)) continue;

            $dist = $this->cosineDistance($embedding, $dbVector);
            if ($dist < $mejorDist) {
                $mejorDist = $dist;
                $mejor = $alumno;
            }
        }

        return ['alumno' => $mejor, 'distancia' => $mejorDist];
    }

    private function recargarServidorPython()
    {
        try {
            $ch = curl_init('http://127.0.0.1:5001/reload');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // Silencioso: si el servidor no esta corriendo, no importa
        }
    }

    public function registrarRostro(Request $request)
    {
        $resultadoLog = '';
        $dniLog = '';
        try {
            $data = $request->validate([
                'dni'     => 'required|string',
                'nombre'  => 'required|string',
                'carrera' => 'required|string',
                'aula_id' => 'nullable|integer',
                'foto'    => 'required|image',
            ]);

            $dniLog = $data['dni'];

            $existente = Alumno::where('dni', $data['dni'])->first();
            if ($existente) {
                $resultadoLog = 'Registro fallido: DNI ya registrado';
                RegistroAcceso::create(['dni' => $dniLog, 'resultado' => $resultadoLog, 'distancia' => null]);
                return response()->json([
                    'detalle' => 'Ya existe un alumno registrado con el DNI ' . $data['dni'],
                ], 409);
            }

            $foto = $request->file('foto');
            $filename = $data['dni'] . '_' . time() . '.jpg';

            $tempDir = sys_get_temp_dir();
            $imagePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            $foto->move($tempDir, $filename);

            if (!file_exists($imagePath)) {
                $resultadoLog = 'Registro fallido: No se pudo guardar imagen temporal';
                RegistroAcceso::create(['dni' => $dniLog, 'resultado' => $resultadoLog, 'distancia' => null]);
                return response()->json([
                    'detalle' => 'No se pudo guardar la imagen temporal',
                ], 500);
            }

            $output = $this->llamarServidorRegistrar($imagePath);
            if (!$output || !isset($output['success']) || !$output['success'] || !isset($output['embedding'])) {
                if (file_exists($imagePath)) unlink($imagePath);
                $detalle = $output['error'] ?? 'Servidor de reconocimiento facial no disponible';
                $resultadoLog = 'Registro fallido: ' . $detalle;
                RegistroAcceso::create(['dni' => $dniLog, 'resultado' => $resultadoLog, 'distancia' => null]);
                return response()->json([
                    'detalle' => $this->sanitizar($detalle),
                ], 400);
            }

            $embedding = $output['embedding'];

            $busqueda = $this->buscarAlumnoPorEmbedding($embedding);
            if ($busqueda['alumno'] && $busqueda['distancia'] < 0.35) {
                if (file_exists($imagePath)) unlink($imagePath);
                $resultadoLog = 'Registro fallido: Rostro ya registrado con DNI ' . $busqueda['alumno']->dni;
                RegistroAcceso::create(['dni' => $dniLog, 'resultado' => $resultadoLog, 'distancia' => null]);
                return response()->json([
                    'detalle' => 'Este rostro ya está registrado con el DNI ' . $busqueda['alumno']->dni . ' (' . $busqueda['alumno']->nombre . ')',
                ], 409);
            }

            $storedPath = storage_path('app/public/fotos/' . $filename);
            if (!file_exists(dirname($storedPath))) {
                mkdir(dirname($storedPath), 0755, true);
            }
            rename($imagePath, $storedPath);

            $vectorStr = '[' . implode(',', $embedding) . ']';

            Alumno::create([
                'dni'           => $data['dni'],
                'nombre'        => $data['nombre'],
                'carrera'       => $data['carrera'],
                'aula_id'       => $data['aula_id'] ?? null,
                'foto_path'     => $filename,
                'vector_rostro' => \DB::raw("'$vectorStr'::vector"),
            ]);

            // Notificar al servidor Python para recargar embeddings
            $this->recargarServidorPython();

            $resultadoLog = 'Registro exitoso';
            RegistroAcceso::create(['dni' => $dniLog, 'resultado' => $resultadoLog, 'distancia' => null]);

            return response()->json(['mensaje' => 'Alumno registrado correctamente']);
        } catch (\Exception $e) {
            if (!$resultadoLog) {
                $resultadoLog = 'Registro fallido: Error interno - ' . $e->getMessage();
                RegistroAcceso::create(['dni' => $dniLog, 'resultado' => $resultadoLog, 'distancia' => null]);
            }
            return response()->json([
                'detalle' => $this->sanitizar('Error interno: ' . $e->getMessage()),
            ], 500);
        }
    }

    public function verificarRostro(Request $request)
    {
        $request->validate(['foto' => 'required|image']);
        $foto = $request->file('foto');
        $filename = 'temp_' . time() . '.jpg';

        $tempDir = sys_get_temp_dir();
        $imagePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
        $foto->move($tempDir, $filename);

        $serverResult = $this->llamarServidorPython($imagePath);

        if (file_exists($imagePath)) unlink($imagePath);

        if (!$serverResult) {
            $resultadoLog = 'Verificación fallida: Servidor de reconocimiento facial no disponible';
            RegistroAcceso::create(['dni' => '—', 'resultado' => $resultadoLog, 'distancia' => null]);
            return response()->json([
                'detalle' => 'Servidor de reconocimiento facial no disponible',
            ], 503);
        }

        if (empty($serverResult['rostros'])) {
            return response()->json([
                'detalle' => 'No se detectó rostro',
            ], 400);
        }

        $rostro = $serverResult['rostros'][0];

        if ($rostro['conocido']) {
            $resultado = 'Verificación exitosa';
            $exitoso = true;
            $nombre = $rostro['nombre'];
            $carrera = $rostro['carrera'];
            $aulaNombre = $rostro['aula_id'] ? (\App\Models\Aula::find($rostro['aula_id'])?->nombre ?? '—') : '—';
            $dni = $rostro['dni'];
            $fotoUrl = $rostro['foto_path'] ? asset('storage/fotos/' . $rostro['foto_path']) : null;
            $mejorDist = $rostro['distancia'];
        } else {
            $resultado = 'Verificación fallida: Coincidencia no encontrada';
            $exitoso = false;
            $nombre = '—';
            $carrera = '—';
            $aulaNombre = '—';
            $dni = '—';
            $fotoUrl = null;
            $mejorDist = 1.0;
        }

        RegistroAcceso::create([
            'dni'       => $dni,
            'resultado' => $resultado,
            'distancia' => $mejorDist,
        ]);

        return response()->json([
            'exitoso'    => $exitoso,
            'mensaje'    => $resultado,
            'nombre'     => $nombre,
            'carrera'    => $carrera,
            'aula'       => $aulaNombre,
            'dni'        => $dni,
            'foto_url'   => $fotoUrl,
            'distancia'  => $mejorDist,
            'timestamp'  => now()->toDateTimeString(),
        ]);
    }

    public function detectarRostroSimple(Request $request)
    {
        $request->validate(['file' => 'required|image']);
        $foto = $request->file('file');
        $filename = 'temp_detect_' . time() . '.jpg';

        $tempDir = sys_get_temp_dir();
        $imagePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
        $foto->move($tempDir, $filename);

        if (!file_exists($imagePath)) {
            return response()->json([
                'rostro_detectado' => false,
                'mensaje' => 'Error interno: no se pudo guardar imagen temporal',
            ]);
        }

        $serverResult = $this->llamarServidorPython($imagePath);
        if (file_exists($imagePath)) unlink($imagePath);

        if (!$serverResult) {
            return response()->json([
                'rostro_detectado' => false,
                'mensaje' => 'Servidor de reconocimiento facial no disponible',
            ], 503);
        }

        $detectado = !empty($serverResult['rostros']);
        return response()->json([
            'rostro_detectado' => $detectado,
            'mensaje'          => $detectado ? 'Rostro listo para registrar' : 'Rostro no detectado',
        ]);
    }

    public function verificarMasivo(Request $request)
    {
        $request->validate(['foto' => 'required|image']);
        $foto = $request->file('foto');
        $filename = 'temp_masivo_' . time() . '.jpg';

        $tempDir = sys_get_temp_dir();
        $imagePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
        $foto->move($tempDir, $filename);

        $serverResult = $this->llamarServidorPython($imagePath);
        if (file_exists($imagePath)) unlink($imagePath);

        if (!$serverResult) {
            return response()->json(['conocido' => false, 'mensaje' => 'Servidor de reconocimiento facial no disponible'], 503);
        }

        return $this->procesarResultadoMasivo($serverResult, $request);
    }

    private function llamarServidorPython(string $imagePath): ?array
    {
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) return null;

        $ch = curl_init('http://127.0.0.1:5001/verificar');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/octet-stream']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['rostros'])) {
                return $decoded;
            }
        }
        return null;
    }

    private function llamarServidorRegistrar(string $imagePath): ?array
    {
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) return null;

        $ch = curl_init('http://127.0.0.1:5001/registrar');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/octet-stream']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['success'])) {
                return $decoded;
            }
        }
        return null;
    }

    private function procesarResultadoMasivo(array $output, Request $request)
    {
        $rostros = $output['rostros'] ?? [];
        if (empty($rostros)) {
            return response()->json(['conocido' => false, 'mensaje' => 'No se detectó rostro'], 400);
        }

        $usuario = $request->header('X-Coordinador-Usuario');
        $misAulasIds = [];
        if ($usuario) {
            $coordinador = \App\Models\Coordinador::where('usuario', $usuario)->with('aulas')->first();
            if ($coordinador) {
                $misAulasIds = $coordinador->aulas->pluck('id')->toArray();
            }
        }

        $resultados = [];
        foreach ($rostros as $rostro) {
            $bbox = $rostro['bbox'];
            if ($rostro['conocido'] ?? false) {
                $esMiAula = in_array($rostro['aula_id'], $misAulasIds);
                $resultados[] = [
                    'conocido'   => true,
                    'dni'        => $rostro['dni'],
                    'nombre'     => $rostro['nombre'],
                    'carrera'    => $rostro['carrera'],
                    'aula'       => $rostro['aula_id'] ? (\App\Models\Aula::find($rostro['aula_id'])?->nombre ?? '—') : '—',
                    'aula_id'    => $rostro['aula_id'],
                    'estado'     => $rostro['estado'],
                    'es_mi_aula' => $esMiAula,
                    'bbox'       => $bbox,
                    'distancia'  => $rostro['distancia'],
                    'confianza'  => $rostro['confianza'],
                ];
            } else {
                $resultados[] = [
                    'conocido' => false,
                    'bbox'     => $bbox,
                ];
            }
        }

        return response()->json([
            'rostros' => $resultados,
            'mensaje' => count($resultados) . ' rostro(s) detectado(s)',
        ]);
    }
}
