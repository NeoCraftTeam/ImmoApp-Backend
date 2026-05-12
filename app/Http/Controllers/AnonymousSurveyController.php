<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SubmitAnonymousSurveyAction;
use App\Http\Requests\AnonymousSurveySubmitRequest;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AnonymousSurveyController
{
    /** List all active, public surveys. */
    public function index(): View
    {
        $surveys = Survey::query()
            ->publiclyVisible()
            ->withCount('questions')
            ->latest()
            ->get();

        return view('surveys.index', compact('surveys'));
    }

    /** Show a single survey form (or already-answered message). */
    public function show(Survey $survey, Request $request, SubmitAnonymousSurveyAction $action): View
    {
        abort_unless($survey->is_active && $survey->is_public, 404);

        $alreadySubmitted = $action->alreadySubmitted($survey, $request);

        $survey->load('questions');

        return view('surveys.show', compact('survey', 'alreadySubmitted'));
    }

    /** Handle form submission. */
    public function submit(
        Survey $survey,
        AnonymousSurveySubmitRequest $request,
        SubmitAnonymousSurveyAction $action,
    ): RedirectResponse {
        abort_unless($survey->is_active && $survey->is_public, 404);

        if ($action->alreadySubmitted($survey, $request)) {
            return redirect()->route('surveys.thankyou', $survey)
                ->with('already_submitted', true);
        }

        $action->execute($survey, $request->validated()['answers'], $request);

        return redirect()->route('surveys.thankyou', $survey);
    }

    /** Thank-you / confirmation page. */
    public function thankYou(Survey $survey): View
    {
        return view('surveys.thankyou', compact('survey'));
    }
}
