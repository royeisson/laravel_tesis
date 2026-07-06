<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegistroAcceso;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function logs(Request $request)
    {
        $query = RegistroAcceso::orderBy('id', 'desc');

        if ($request->dni) {
            $query->where('dni', $request->dni);
        }

        $logs = $query->get()->map(function ($r) {
            return [
                'id'            => $r->id,
                'dni'           => $r->dni,
                'alumno_nombre' => $r->dni,
                'alumno_dni'    => $r->dni,
                'resultado'     => $r->resultado,
                'distancia'     => $r->distancia,
                'creado_en'     => $r->fecha?->toDateTimeString(),
            ];
        });

        return response()->json($logs);
    }

    public function exportar()
    {
        $logs = RegistroAcceso::orderBy('id', 'desc')->get();
        $csv = "ID,DNI,Resultado,Distancia,Fecha\n";
        foreach ($logs as $r) {
            $csv .= "{$r->id},{$r->dni},{$r->resultado},{$r->distancia},{$r->fecha}\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=reporte.csv',
        ]);
    }
}
