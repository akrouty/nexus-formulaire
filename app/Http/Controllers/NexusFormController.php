<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class NexusFormController extends Controller
{

    public function showLanding()
    {
        return view('nexus.landing');
    }


    public function showForm()
    {
        return view('nexus.index');
    }


    public function analyzeIntention(Request $request)
{
    $validated = $request->validate([
        'text'            => 'nullable|string|max:500',
        'missionOverride' => 'nullable|in:don,benevolat,infos,contact',
    ]);

    $text = $validated['text'] ?? '';
    $missionOverride = $validated['missionOverride'] ?? null;

    if ($missionOverride) {
        $mission = $missionOverride;
    } else {
        $mission = $this->detectMissionFromText($text);
    }

    $fields = $this->getFieldsForMission($mission);

    return response()->json([
        'mission' => $mission,
        'fields'  => $fields,
    ]);
}


    public function submit(Request $request)
{
    $mission = $request->input('mission');
    $data    = $request->input('data', []);

    // 1) Honeypot anti-spam : le champ "website" doit rester vide
    if (!empty($data['website'] ?? '')) {
        return response()->json([
            'error' => 'Spam détecté.',
        ], 400);
    }

    // 2) Vérifier le reCAPTCHA (le token est dans $data['g-recaptcha-response'])
    $recaptchaToken = $data['g-recaptcha-response'] ?? null;

    if (!$recaptchaToken) {
        return response()->json([
            'error' => 'Merci de valider le reCAPTCHA.',
        ], 400);
    }

    $secret = env('RECAPTCHA_SECRET_KEY');
    $verifyUrl = "https://www.google.com/recaptcha/api/siteverify"
        ."?secret={$secret}&response={$recaptchaToken}";

    $response = file_get_contents($verifyUrl);
    $responseKeys = json_decode($response, true);
    


    if (empty($responseKeys['success'])) {
        return response()->json([
            'error' => 'Échec de vérification du reCAPTCHA.',
        ], 400);
    }

    // 3) Validation Laravel des champs du formulaire (SANS l’ancien captcha)
    $rules = [
        'data.email' => 'required|email',
        // plus de data.captcha_answer ici
    ];

    switch ($mission) {
        case 'don':
            $rules = array_merge($rules, [
                'data.nom'       => 'required|string|min:2',
                'data.montant'   => 'required',
                'data.type_don'  => 'required',
                'data.projet'    => 'required',
            ]);
            break;

        case 'benevolat':
            $rules = array_merge($rules, [
                'data.nom'           => 'required|string|min:2',
                'data.type_mission'  => 'required',
                'data.disponibilites'=> 'required|string|min:3',
                'data.competences'   => 'required|string|min:3',
            ]);
            break;

        case 'infos':
            $rules = array_merge($rules, [
                'data.email'   => 'required|email',
                'data.sujet'   => 'required|string|min:3',
                'data.question'=> 'required|string|min:5',
            ]);
            break;

        case 'contact':
        default:
            $rules = array_merge($rules, [
                'data.nom'     => 'required|string|min:2',
                'data.sujet'   => 'required|string|min:3',
                'data.message' => 'required|string|min:5',
            ]);
            break;
    }

    $validator = \Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json([
            'error'   => 'Validation échouée.',
            'details' => $validator->errors(),
        ], 422);
    }

    // 4) Tout est OK → générer le message de confirmation + retourner le JSON
    $message = $this->generateConfirmationMessage($mission, $data);

    return response()->json([
        'ok'      => true,
        'message' => $message,
    ]);
}

private function detectMissionFromText(string $text): string
{
    $trimmed = trim($text);

    // Si vide → contact
    if ($trimmed === '') {
        return 'contact';
    }

    $apiKey = env('GEMINI_API_KEY');

    // Si pas de clé → fallback local
    if (!$apiKey) {
        return $this->detectMissionWithKeywords($text);
    }

    try {
        // L'API Gemini attend la clé dans l'URL, pas dans le body
        $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        $response = Http::post($url, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' =>
                                "Tu es un classificateur pour un formulaire d'association qui soutient un village numérique résistant (démarche NIRD).\n"
                                ."Lis la phrase de l'utilisateur en français et réponds UNIQUEMENT par un seul mot parmi :\n"
                                ."- don\n- benevolat\n- infos\n- contact\n\n"
                                ."Ne renvoie rien d'autre (pas de phrase, pas de ponctuation, pas de commentaire).\n\n"
                                ."Texte de l'utilisateur : ".$trimmed,
                        ],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            // Si Gemini renvoie 4xx / 5xx → on retombe sur les mots-clés
            return $this->detectMissionWithKeywords($text);
        }

        $json = $response->json();

        // Récupérer le texte généré par Gemini
        $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? 'contact';
        $label   = strtolower(trim($content));

        $allowed = ['don', 'benevolat', 'infos', 'contact'];
        if (!in_array($label, $allowed, true)) {
            return $this->detectMissionWithKeywords($text);
        }

        return $label;

    } catch (\Throwable $e) {
        // En cas d'erreur réseau / exception → on ne casse pas l'appli
        return $this->detectMissionWithKeywords($text);
    }
}
/**
 * Fallback local : détection par mots-clés si l'API IA n'est pas dispo.
 */
private function detectMissionWithKeywords(string $text): string
{
    $t = mb_strtolower($text);

    if ($t === '') {
        return 'contact';
    }

    if (str_contains($t, 'don') || str_contains($t, 'donner') || str_contains($t, 'donation') || str_contains($t, 'finance')) {
        return 'don';
    }

    if (str_contains($t, 'bénévole') || str_contains($t, 'benevole') || str_contains($t, 'volontaire') || str_contains($t, 'aider') || str_contains($t, 'participer')) {
        return 'benevolat';
    }

    if (str_contains($t, 'info') || str_contains($t, 'infos') || str_contains($t, 'information') || str_contains($t, 'renseignement')) {
        return 'infos';
    }

    if (str_contains($t, 'contact') || str_contains($t, 'appeler') || str_contains($t, 'parler')) {
        return 'contact';
    }

    return 'contact';
}


    private function getFieldsForMission(string $mission): array
    {
        switch ($mission) {
            case 'don':
                return [
                    [
                        'name'     => 'nom',
                        'label'    => 'Votre nom',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'name'     => 'email',
                        'label'    => 'Votre e-mail',
                        'type'     => 'email',
                        'required' => true,
                    ],
                    [
                        'name'     => 'montant',
                        'label'    => 'Montant du don',
                        'type'     => 'select',
                        'required' => true,
                        'options'  => ['5 €', '10 €', '20 €', 'Autre'],
                    ],
                    [
                        'name'     => 'type_don',
                        'label'    => 'Type de don',
                        'type'     => 'radio',
                        'required' => true,
                        'options'  => ['Ponctuel', 'Mensuel'],
                    ],
                    [
                        'name'     => 'projet',
                        'label'    => 'Projet soutenu',
                        'type'     => 'select',
                        'required' => true,
                        'options'  => [
                            'Migration vers des logiciels libres dans les écoles',
                            'Réemploi du matériel informatique scolaire',
                            'Sensibilisation NIRD auprès des élèves',
                        ],
                    ],
                ];

            case 'benevolat':
                return [
                    [
                        'name'     => 'nom',
                        'label'    => 'Votre nom',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'name'     => 'email',
                        'label'    => 'Votre e-mail',
                        'type'     => 'email',
                        'required' => true,
                    ],
                    [
                        'name'     => 'type_mission',
                        'label'    => 'Type de mission',
                        'type'     => 'select',
                        'required' => true,
                        'options'  => [
                            'Ateliers logiciels libres',
                            'Accompagnement des équipes pédagogiques',
                            'Communication / sensibilisation',
                        ],
                    ],
                    [
                        'name'     => 'disponibilites',
                        'label'    => 'Vos disponibilités',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'name'     => 'competences',
                        'label'    => 'Vos compétences / envies',
                        'type'     => 'textarea',
                        'required' => true,
                    ],
                ];

            case 'infos':
                return [
                    [
                        'name'     => 'nom',
                        'label'    => 'Votre nom (optionnel)',
                        'type'     => 'text',
                        'required' => false,
                    ],
                    [
                        'name'     => 'email',
                        'label'    => 'Votre e-mail',
                        'type'     => 'email',
                        'required' => true,
                    ],
                    [
                        'name'     => 'sujet',
                        'label'    => 'Sujet de votre question',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'name'     => 'question',
                        'label'    => 'Votre question',
                        'type'     => 'textarea',
                        'required' => true,
                    ],
                ];

            case 'contact':
            default:
                return [
                    [
                        'name'     => 'nom',
                        'label'    => 'Votre nom',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'name'     => 'email',
                        'label'    => 'Votre e-mail',
                        'type'     => 'email',
                        'required' => true,
                    ],
                    [
                        'name'     => 'sujet',
                        'label'    => 'Sujet',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'name'     => 'message',
                        'label'    => 'Votre message',
                        'type'     => 'textarea',
                        'required' => true,
                    ],
                ];
        }
    }

    /**
     * Génère un message de confirmation personnalisé.
     *
     * Vous pouvez remplacer cette logique par un appel vers un modèle IA
     * qui renverrait un texte plus riche à partir de la mission + données.
     */
   /**
 * Génère un message de confirmation personnalisé avec IA (Gemini).
 * Si l'API n'est pas dispo, on retombe sur une version statique.
 */
private function generateConfirmationMessage(?string $mission, array $data): string
{
    $year = now()->year;
    $nom  = $data['nom'] ?? 'Cher soutien';
    $apiKey = env('GEMINI_API_KEY');

    // =========================
    // 1) TENTATIVE AVEC GEMINI
    // =========================
    if ($apiKey) {
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key={$apiKey}";

            // On construit un petit résumé des infos utiles pour l'IA
            $infosJson = json_encode([
                'mission' => $mission,
                'data'    => $data,
                'year'    => $year,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $prompt = <<<TXT
Tu es Axolotl, la voix de l'association qui anime un "village numérique résistant" basé sur la démarche NIRD (Numérique Inclusif, Responsable et Durable).

Objectif :
- Générer un message de remerciement COURT, chaleureux, en français, à afficher dans une pop-up de confirmation.
- Respecter le ton "jeu vidéo / chevalier du code" mais rester lisible et professionnel.
- NE PAS utiliser de markdown, juste du texte avec des retours à la ligne (\n).
- Inclure :
  - le prénom ou nom de la personne si disponible,
  - la mission (don, bénévolat, infos ou contact),
  - l'année {$year},
  - une courte mention du projet soutenu (si "don"),
  - un clin d'œil au village numérique résistant et à la démarche NIRD,
  - une phrase qui invite à suivre les actions pendant l'année {$year}.

Contexte JSON :
{$infosJson}

Réponds uniquement par le texte du message, sans en-tête, sans "IA :", sans guillemets autour.
TXT;

            $response = Http::post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text && is_string($text) && trim($text) !== '') {
                    // On renvoie directement le texte généré par l'IA
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            // En cas d'erreur, on tombe sur le fallback ci-dessous
            // Optionnel : Log::error('Gemini confirmation error: '.$e->getMessage());
        }
    }

    // =========================
    // 2) FALLBACK STATIQUE
    // =========================
    if ($mission === 'don') {
        $montant = $data['montant'] ?? 'votre don';
        $projet  = $data['projet'] ?? 'notre village numérique résistant';

        return "Un immense GG, {$nom} ! 🏆\n"
            ."Ton don de {$montant} en {$year} est une vraie bénédiction pour notre village numérique résistant 💻🏘️.\n"
            ."Grâce à toi, nous pouvons avancer sur le projet « {$projet} » cette année {$year}.\n"
            ."Ton soutien en {$year} est crucial pour aider les établissements scolaires à réduire leur dépendance aux Big Tech "
            ."et à entrer dans la démarche NIRD (Numérique Inclusif, Responsable et Durable). 🌱\n"
            ."Reste connecté·e pour suivre nos exploits tout au long de l'année {$year} ! 🚀";
    }

    if ($mission === 'benevolat') {
        $typeMission = $data['type_mission'] ?? 'bénévolat';

        return "Salutations, {$nom} ! 👋\n"
            ."Ta décision de rejoindre la Guilde des Bénévoles en {$year} renforce notre village numérique résistant contre les Big Tech 💪.\n"
            ."Ton implication dans « {$typeMission} » va nous aider à accompagner les écoles vers un numérique plus libre, inclusif, responsable et durable (NIRD).\n"
            ."Merci pour ton énergie en {$year}, elle compte vraiment ! ✨";
    }

    if ($mission === 'infos') {
        return "Merci pour ta question, {$nom} ! 💬\n"
            ."Ton besoin d'information en {$year} nous aide à mieux cibler les attentes des établissements qui veulent résister à la dépendance aux Big Tech.\n"
            ."Nos équipes du village numérique NIRD vont revenir vers toi dès que possible avec des éléments concrets.\n"
            ."Reste connecté·e pour suivre l'évolution de nos projets tout au long de l'année {$year} ! 🚀";
    }

    // contact ou défaut
    return "Salutations, {$nom} ! 👋\n"
        ."Ton message a bien été acheminé vers nos serveurs centraux du village numérique résistant 📡.\n"
        ."En {$year}, chaque échange nous aide à construire un numérique plus autonome pour les établissements scolaires, "
        ."loin de la dépendance aux Big Tech.\n"
        ."Nos Agents de Support te répondront sous peu. Merci pour ta contribution à la démarche NIRD ! 🌱";
}}