# Plan d'Implémentation Backend (Laravel) : Module de Sondage

Ce document fournit un plan de développement détaillé pour la création du module de sondage côté backend avec le framework Laravel. Il inclut la conception de la base de données, les routes d'API, la logique du contrôleur et les règles de validation.

## 5.1. Conception de la Base de Données (Migrations)

Trois tables principales sont nécessaires pour gérer les sondages, leurs questions et les réponses des utilisateurs.

### 1. Table `surveys`

Cette table stocke les informations de base sur chaque sondage.

**Fichier : `database/migrations/YYYY_MM_DD_HHMMSS_create_surveys_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
```

### 2. Table `survey_questions`

Cette table stocke chaque question liée à un sondage, son type et ses options.

**Fichier : `database/migrations/YYYY_MM_DD_HHMMSS_create_survey_questions_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained()->onDelete('cascade');
            $table->text('text');
            $table->enum('type', ['multiple_choice', 'checkbox', 'rating', 'text']);
            $table->json('options')->nullable(); // Pour 'multiple_choice' et 'checkbox'
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
```

### 3. Table `survey_responses`

Cette table stocke les réponses individuelles de chaque utilisateur à chaque question.

**Fichier : `database/migrations/YYYY_MM_DD_HHMMSS_create_survey_responses_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('survey_question_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->text('answer'); // Stocke la réponse, JSON pour les checkbox
            $table->timestamps();

            // Empêcher un utilisateur de répondre plusieurs fois à la même question
            $table->unique(['survey_question_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
```

## 5.2. Modèles Eloquent

Les modèles correspondants pour interagir avec ces tables.

**`app/Models/Survey.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'description', 'is_active'];
    protected $casts = ['id' => 'string'];
    public $incrementing = false;

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }
}
```

**`app/Models/SurveyQuestion.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    use HasFactory;
    protected $fillable = ['survey_id', 'text', 'type', 'options', 'order'];
    protected $casts = [
        'id' => 'string',
        'survey_id' => 'string',
        'options' => 'array',
    ];
    public $incrementing = false;
}
```

**`app/Models/SurveyResponse.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    use HasFactory;
    protected $fillable = ['survey_id', 'survey_question_id', 'user_id', 'answer'];
    protected $casts = [
        'id' => 'string',
        'survey_id' => 'string',
        'survey_question_id' => 'string',
        'user_id' => 'string',
    ];
    public $incrementing = false;
}
```

## 5.3. Routes API

Les points d'accès pour le frontend.

**`routes/api.php`**
```php
use App\Http\Controllers\SurveyController;

// ... autres routes

Route::prefix('surveys')->group(function () {
    // Pour les administrateurs (gestion complète des sondages)
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [SurveyController::class, 'index']);
        Route::post('/', [SurveyController::class, 'store']);
        Route::get('/{survey}/results', [SurveyController::class, 'results']);
        // Ajouter PUT, DELETE pour la gestion complète
    });

    // Pour les utilisateurs authentifiés
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{survey}', [SurveyController::class, 'show']);
        Route::post('/{survey}/responses', [SurveyController::class, 'submitResponse']);
    });
});
```

## 5.4. Logique du Contrôleur

La logique métier pour gérer les requêtes.

**`app/Http/Controllers/SurveyController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SurveyController extends Controller
{
    /**
     * (Admin) Affiche la liste de tous les sondages.
     */
    public function index()
    {
        return Survey::withCount('questions')->latest()->paginate();
    }

    /**
     * (Admin) Crée un nouveau sondage avec ses questions.
     */
    public function store(Request $request)
    {
        // ... Logique de validation complexe pour créer un sondage et ses questions
    }

    /**
     * (User) Affiche un sondage spécifique avec ses questions.
     */
    public function show(Survey $survey)
    {
        $survey->load('questions');
        return response()->json($survey);
    }

    /**
     * (User) Soumet les réponses à un sondage.
     */
    public function submitResponse(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'uuid', Rule::exists('survey_questions', 'id')->where('survey_id', $survey->id)],
            'answers.*.answer' => ['required'],
        ]);

        $user = $request->user();

        // Utiliser une transaction pour garantir l'intégrité des données
        DB::transaction(function () use ($validated, $survey, $user) {
            foreach ($validated['answers'] as $response) {
                // Valider la réponse en fonction du type de question
                $question = $survey->questions()->find($response['question_id']);
                if (!$question) continue; // Devrait être empêché par la validation

                // Exemple de validation de type
                if ($question->type === 'rating' && !in_array($response['answer'], [1, 2, 3, 4, 5])) {
                    throw ValidationException::withMessages(['answer' => 'La note doit être entre 1 et 5.']);
                }

                $survey->responses()->updateOrCreate(
                    [
                        'survey_question_id' => $response['question_id'],
                        'user_id' => $user->id,
                    ],
                    ['answer' => is_array($response['answer']) ? json_encode($response['answer']) : $response['answer']]
                );
            }
        });

        return response()->json(['message' => 'Merci d\'avoir participé !'], 201);
    }

    /**
     * (Admin) Récupère les résultats agrégés d'un sondage.
     */
    public function results(Survey $survey)
    {
        // ... Logique d'agrégation des réponses
    }
}
```

## 5.5. Règles de Validation

Un exemple de règles de validation pour la soumission des réponses. La validation peut être affinée directement dans la méthode `submitResponse` pour vérifier que la réponse correspond au type de la question.

```php
// Dans la méthode submitResponse du SurveyController

$request->validate([
    'answers' => ['required', 'array', 'min:1'],
    'answers.*.question_id' => [
        'required',
        'uuid',
        // S'assurer que la question appartient bien au sondage en cours
        Rule::exists('survey_questions', 'id')->where('survey_id', $survey->id)
    ],
    'answers.*.answer' => ['required'], // La validation de base
]);

// Ensuite, une validation plus fine par type de question dans la boucle
foreach ($validated['answers'] as $response) {
    $question = $survey->questions()->find($response['question_id']);

    switch ($question->type) {
        case 'rating':
            if (!is_numeric($response['answer']) || $response['answer'] < 1 || $response['answer'] > 5) {
                // Gérer l'erreur
            }
            break;
        case 'checkbox':
            if (!is_array($response['answer']) || count(array_diff($response['answer'], $question->options)) > 0) {
                // Gérer l'erreur (une option soumise n'existe pas)
            }
            break;
        // ... autres cas
    }
}
```
