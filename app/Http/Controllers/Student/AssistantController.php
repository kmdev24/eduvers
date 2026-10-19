<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\StudentAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The help assistant in the corner of the student pages. */
class AssistantController extends Controller
{
    public function __invoke(Request $request, StudentAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($assistant->answer($data['question']));
    }
}
