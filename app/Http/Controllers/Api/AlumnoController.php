<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    public function index(Request $request)
    {
        $query = Alumno::with('aula')
            ->select(['id', 'dni', 'nombre', 'carrera', 'aula_id', 'estado', 'foto_path']);

        if ($request->aula_id) {
            $query->where('aula_id', $request->aula_id);
        }

        $alumnos = $query->orderBy('id', 'desc')->get()->map(function ($a) {
            return [
                'id'          => $a->id,
                'dni'         => $a->dni,
                'nombre'      => $a->nombre,
                'carrera'     => $a->carrera,
                'aula_id'     => $a->aula_id,
                'aula_nombre' => $a->aula?->nombre,
                'estado'      => $a->estado,
                'foto_url'    => $a->foto_path ? asset('storage/fotos/' . $a->foto_path) : null,
                'foto_path'   => $a->foto_path,
            ];
        });

        if ($request->sin_paginacion) {
            return response()->json($alumnos);
        }

        return response()->json($alumnos);
    }

    public function show($id)
    {
        $a = Alumno::with('aula')->findOrFail($id);
        return response()->json([
            'id'          => $a->id,
            'dni'         => $a->dni,
            'nombre'      => $a->nombre,
            'carrera'     => $a->carrera,
            'aula_id'     => $a->aula_id,
            'aula_nombre' => $a->aula?->nombre,
            'estado'      => $a->estado,
            'foto_url'    => $a->foto_path ? asset('storage/fotos/' . $a->foto_path) : null,
            'foto_path'   => $a->foto_path,
        ]);
    }

    public function update(Request $request, $id)
    {
        $alumno = Alumno::findOrFail($id);
        $data = $request->validate([
            'nombre'  => 'sometimes|string',
            'carrera' => 'sometimes|string',
            'aula_id' => 'sometimes|nullable|integer',
        ]);
        $alumno->update($data);
        return response()->json(['mensaje' => 'Alumno actualizado']);
    }

    public function mover(Request $request, $id)
    {
        $data = $request->validate(['aula_id' => 'nullable|integer']);
        $alumno = Alumno::findOrFail($id);
        $alumno->update(['aula_id' => $data['aula_id'] ?? null]);
        return response()->json(['mensaje' => 'Alumno movido']);
    }

    public function destroy($id)
    {
        $alumno = Alumno::findOrFail($id);
        if ($alumno->foto_path) {
            $path = storage_path('app/public/fotos/' . $alumno->foto_path);
            if (file_exists($path)) unlink($path);
        }
        $alumno->delete();

        // Notificar al servidor Python para recargar embeddings
        $this->recargarServidorPython();

        return response()->json(['mensaje' => 'Alumno eliminado']);
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
            // Silencioso
        }
    }
}
