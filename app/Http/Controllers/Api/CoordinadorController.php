<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coordinador;
use App\Models\Aula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CoordinadorController extends Controller
{
    public function index()
    {
        $coordinadores = Coordinador::with('aulas')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'usuario' => $c->usuario,
                'aulas' => $c->aulas->map(function ($a) {
                    return ['id' => $a->id, 'nombre' => $a->nombre];
                }),
            ];
        });
        return response()->json($coordinadores);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string',
            'usuario' => 'required|string|unique:coordinadores,usuario',
            'password' => 'required|string|min:6',
        ]);

        $coordinador = Coordinador::create([
            'nombre' => $data['nombre'],
            'usuario' => $data['usuario'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json(['id' => $coordinador->id, 'mensaje' => 'Coordinador creado']);
    }

    public function update(Request $request, $id)
    {
        $coordinador = Coordinador::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'sometimes|string',
            'password' => 'sometimes|string|min:6',
        ]);

        if (isset($data['nombre'])) $coordinador->nombre = $data['nombre'];
        if (isset($data['password'])) $coordinador->password = Hash::make($data['password']);
        $coordinador->save();

        return response()->json(['mensaje' => 'Coordinador actualizado']);
    }

    public function destroy($id)
    {
        $coordinador = Coordinador::findOrFail($id);
        $coordinador->delete();
        return response()->json(['mensaje' => 'Coordinador eliminado']);
    }

    public function asignarAulas(Request $request, $id)
    {
        $coordinador = Coordinador::findOrFail($id);
        $data = $request->validate([
            'aula_ids' => 'required|array',
            'aula_ids.*' => 'integer|exists:aulas,id',
        ]);

        // Verificar que ninguna aula esté asignada a otro coordinador
        $ocupadas = \DB::table('coordinador_aula')
            ->whereIn('aula_id', $data['aula_ids'])
            ->where('coordinador_id', '!=', $id)
            ->pluck('aula_id');

        if ($ocupadas->isNotEmpty()) {
            return response()->json([
                'error' => 'Algunas aulas ya están asignadas a otro coordinador',
                'aulas_ocupadas' => $ocupadas->values(),
            ], 422);
        }

        $coordinador->aulas()->sync($data['aula_ids']);

        return response()->json(['mensaje' => 'Aulas asignadas']);
    }

    public function misAulas(Request $request)
    {
        $usuario = $request->header('X-Coordinador-Usuario');
        if (!$usuario) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $coordinador = Coordinador::where('usuario', $usuario)->with(['aulas' => function ($q) {
            $q->withCount('alumnos');
        }])->first();
        if (!$coordinador) {
            return response()->json(['error' => 'Coordinador no encontrado'], 404);
        }

        return response()->json($coordinador->aulas->map(function ($a) {
            return [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'total_alumnos' => $a->alumnos_count,
            ];
        }));
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        $coordinador = Coordinador::where('usuario', $data['usuario'])->first();
        if (!$coordinador || !Hash::check($data['password'], $coordinador->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        return response()->json([
            'nombre' => $coordinador->nombre,
            'usuario' => $coordinador->usuario,
            'rol' => 'coordinador',
        ]);
    }
}
