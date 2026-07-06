<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aula;
use Illuminate\Http\Request;

class AulaController extends Controller
{
    public function index()
    {
        $aulas = Aula::with(['coordinadores'])
            ->withCount('alumnos')
            ->orderBy('id')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'total_alumnos' => $a->alumnos_count,
                    'coordinadores' => $a->coordinadores->map(function ($c) {
                        return ['id' => $c->id, 'nombre' => $c->nombre];
                    }),
                ];
            });
        return response()->json($aulas);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|unique:aulas,nombre',
        ]);
        $aula = Aula::create($data);
        return response()->json($aula, 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nombre' => 'required|string|unique:aulas,nombre,' . $id,
        ]);
        $aula = Aula::findOrFail($id);
        $aula->update($data);
        return response()->json($aula);
    }

    public function destroy($id)
    {
        $aula = Aula::findOrFail($id);
        // Desasignar alumnos antes de eliminar el aula
        \App\Models\Alumno::where('aula_id', $id)->update(['aula_id' => null]);
        $aula->delete();
        return response()->json(['mensaje' => 'Aula eliminada']);
    }
}
