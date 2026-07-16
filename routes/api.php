<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AulaController;
use App\Http\Controllers\Api\AlumnoController;
use App\Http\Controllers\Api\VerificacionController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\CoordinadorController;
use App\Http\Controllers\Api\AsistenciaController;
use App\Http\Controllers\Api\GuiaController;
use App\Http\Controllers\Api\ExcelController;

Route::get('/aulas', [AulaController::class, 'index']);
Route::post('/aulas', [AulaController::class, 'store']);
Route::put('/aulas/{id}', [AulaController::class, 'update']);
Route::delete('/aulas/{id}', [AulaController::class, 'destroy']);

Route::get('/alumnos', [AlumnoController::class, 'index']);
Route::get('/alumnos/{id}', [AlumnoController::class, 'show']);
Route::put('/alumnos/{id}', [AlumnoController::class, 'update']);
Route::put('/alumnos/{id}/mover', [AlumnoController::class, 'mover']);
Route::delete('/alumnos/{id}', [AlumnoController::class, 'destroy']);

Route::post('/registrar-rostro', [VerificacionController::class, 'registrarRostro']);
Route::post('/verificar-rostro', [VerificacionController::class, 'verificarRostro']);
Route::post('/verificar-masivo', [VerificacionController::class, 'verificarMasivo']);
Route::post('/detectar-rostro-simple', [VerificacionController::class, 'detectarRostroSimple']);

Route::get('/reportes/logs', [ReporteController::class, 'logs']);
Route::get('/reportes/exportar', [ReporteController::class, 'exportar']);

Route::get('/coordinadores', [CoordinadorController::class, 'index']);
Route::post('/coordinadores', [CoordinadorController::class, 'store']);
Route::put('/coordinadores/{id}', [CoordinadorController::class, 'update']);
Route::delete('/coordinadores/{id}', [CoordinadorController::class, 'destroy']);
Route::post('/coordinadores/{id}/aulas', [CoordinadorController::class, 'asignarAulas']);
Route::get('/coordinadores/mis-aulas', [CoordinadorController::class, 'misAulas']);
Route::post('/coordinadores/login', [CoordinadorController::class, 'login']);

Route::get('/asistencia/aula/{aulaId}', [AsistenciaController::class, 'listarPorAula']);
Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar']);
Route::post('/asistencia/reset', [AsistenciaController::class, 'reset']);

Route::get('/guias', [GuiaController::class, 'index']);
Route::post('/guias', [GuiaController::class, 'store']);
Route::put('/guias/{id}', [GuiaController::class, 'update']);
Route::delete('/guias/{id}', [GuiaController::class, 'destroy']);
Route::post('/guias/login', [GuiaController::class, 'login']);

Route::get('/excel/exportar', [ExcelController::class, 'exportar']);
Route::post('/excel/importar', [ExcelController::class, 'importar']);
