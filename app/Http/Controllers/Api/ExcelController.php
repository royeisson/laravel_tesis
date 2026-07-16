<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExcelController extends Controller
{
    public function exportar()
    {
        $alumnos = Alumno::orderBy('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alumnos');

        // Encabezados: solo 5 columnas
        $headers = ['DNI', 'Nombre', 'Carrera', 'Foto', 'Embedding'];
        $cols = ['A', 'B', 'C', 'D', 'E'];

        foreach ($headers as $i => $h) {
            $col = $cols[$i];
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFont()->setSize(12);
            $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Fila de encabezados mas alta
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Borde del encabezado
        $sheet->getStyle('A1:E1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);

        $fila = 2;
        foreach ($alumnos as $alumno) {
            $sheet->setCellValue('A' . $fila, $alumno->dni);
            $sheet->setCellValue('B' . $fila, $alumno->nombre);
            $sheet->setCellValue('C' . $fila, $alumno->carrera);

            // Columna D: Foto (path en texto + imagen embebida)
            $sheet->setCellValue('D' . $fila, $alumno->foto_path ?? '');

            // Columna E: Embedding
            if ($alumno->vector_rostro) {
                $vec = str_replace(['{', '}'], '', $alumno->vector_rostro);
                $sheet->setCellValue('E' . $fila, $vec);
            } else {
                $sheet->setCellValue('E' . $fila, '');
            }

            // Insertar imagen real en columna D
            if ($alumno->foto_path) {
                $fotoPath = storage_path('app/public/fotos/' . $alumno->foto_path);
                if (file_exists($fotoPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Foto ' . $alumno->nombre);
                    $drawing->setPath($fotoPath);
                    $drawing->setHeight(70);
                    $drawing->setCoordinates('D' . $fila);
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                    $sheet->getRowDimension($fila)->setRowHeight(60);
                }
            }

            $fila++;
        }

        // Estilos: centrar todo, bordes, justificar texto largo
        $ultimaFila = $fila - 1;
        if ($ultimaFila >= 2) {
            $rango = "A2:E{$ultimaFila}";

            // Centrado vertical y horizontal
            $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($rango)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // Justificar nombre y carrera
            $sheet->getStyle("B2:C{$ultimaFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_JUSTIFY);
            $sheet->getStyle("B2:C{$ultimaFila}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B2:C{$ultimaFila}")->getAlignment()->setWrapText(true);

            // Bordes finos en todas las celdas
            $sheet->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(60);

        // Congelar primera fila
        $sheet->freezePane('A2');

        // Generar archivo
        $tempDir = sys_get_temp_dir();
        $filename = 'alumnos_' . date('Y-m-d_His') . '.xlsx';
        $filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('archivo');
        $filepath = $file->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();

            $creados = 0;
            $errores = [];
            $dnisVistos = [];

            $fila = 2;
            while (true) {
                $dni = $sheet->getCell('A' . $fila)->getValue();
                $nombre = $sheet->getCell('B' . $fila)->getValue();
                $carrera = $sheet->getCell('C' . $fila)->getValue();
                $fotoPath = $sheet->getCell('D' . $fila)->getValue();
                $embeddingStr = $sheet->getCell('E' . $fila)->getValue();

                if (empty($dni) && empty($nombre)) break;

                if (empty($dni) || empty($nombre)) {
                    $errores[] = "Fila {$fila}: DNI o nombre vacio";
                    $fila++;
                    continue;
                }

                $dniStr = (string) $dni;

                // Duplicado dentro del mismo Excel
                if (in_array($dniStr, $dnisVistos)) {
                    $errores[] = "Fila {$fila}: DNI {$dniStr} repetido dentro del archivo Excel";
                    $fila++;
                    continue;
                }
                $dnisVistos[] = $dniStr;

                // Duplicado en la base de datos
                $existente = Alumno::where('dni', $dniStr)->first();
                if ($existente) {
                    $errores[] = "Fila {$fila}: DNI {$dniStr} ya existe en la BD - alumno: {$existente->nombre}";
                    $fila++;
                    continue;
                }

                $carreraStr = $carrera ?? '';
                $fotoStr = $fotoPath ?? '';
                $vectorStr = null;

                if (!empty($embeddingStr)) {
                    $vec = trim((string) $embeddingStr);
                    $vectorStr = '[' . $vec . ']';
                }

                Alumno::create([
                    'dni' => $dniStr,
                    'nombre' => $nombre,
                    'carrera' => $carreraStr,
                    'aula_id' => null,
                    'estado' => 'Falto',
                    'foto_path' => $fotoStr ?: null,
                    'vector_rostro' => $vectorStr ? \DB::raw("'$vectorStr'::vector") : null,
                ]);
                $creados++;

                $fila++;
            }

            $this->recargarServidorPython();

            $mensaje = "Importacion completa: {$creados} alumnos creados";
            if (count($errores) > 0) {
                $mensaje .= ", " . count($errores) . " registro(s) omitido(s)";
            }

            return response()->json([
                'mensaje' => $mensaje,
                'creados' => $creados,
                'errores' => $errores,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al leer el archivo: ' . $e->getMessage(),
            ], 422);
        }
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
        }
    }
}