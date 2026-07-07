<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function listarPorAula($aulaId)
    {
        $alumnos = Alumno::where('aula_id', $aulaId)
            ->orderBy('nombre')
            ->get(['id', 'dni', 'nombre', 'carrera', 'estado', 'foto_path']);
        return response()->json($alumnos);
    }

    public function marcar(Request $request)
    {
        $data = $request->validate(['dni' => 'required|string']);
        $alumno = Alumno::where('dni', $data['dni'])->first();
        if (!$alumno) {
            return response()->json(['error' => 'Alumno no encontrado'], 404);
        }
        $alumno->estado = 'Asistió';
        $alumno->save();
        return response()->json(['mensaje' => 'Asistencia registrada', 'alumno' => $alumno]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['aula_id' => 'nullable|integer']);
        $query = Alumno::query();
        if (!empty($data['aula_id'])) {
            $query->where('aula_id', $data['aula_id']);
        }
        $query->update(['estado' => 'Faltó']);
        return response()->json(['mensaje' => 'Asistencias reseteadas']);
    }
}
