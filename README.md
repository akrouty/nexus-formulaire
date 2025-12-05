
# Module Laravel - Le Nexus Connecté : Formulaire augmenté (Défi SFEIR)

Ce dossier contient un **module prêt à intégrer** dans un projet Laravel existant
(Laravel 10 ou 11) pour répondre au défi **« Formulaire augmenté »** de la Nuit de l'Info 2025.

Il respecte :
- une architecture **MVC Laravel** (routes + contrôleur + vue Blade) ;
- un **formulaire d'interaction dynamique** qui s'adapte à la mission ;
- une pseudo-IA intégrée côté backend (facile à brancher sur un vrai endpoint) ;
- une **page de confirmation personnalisée** avec :
  - mission,
  - nom,
  - année courante,
  - références au thème 2025 (village numérique résistant, NIRD, Big Tech) ;
- des éléments d'**accessibilité**, de **sécurité** (honeypot + captcha) et d'**UX**.

---

## 1. Pré-requis

- PHP + Composer installés
- Un projet Laravel existant (ou à créer) :

  ```bash
  composer create-project laravel/laravel nexus-formulaire
  cd nexus-formulaire
  php artisan serve
  ```

---

## 2. Intégration du module dans Laravel

1. **Copier les fichiers** de ce dossier dans votre projet Laravel :

   - `app/Http/Controllers/NexusFormController.php` → dans `app/Http/Controllers/`
   - `resources/views/nexus/index.blade.php` → dans `resources/views/nexus/`
   - `public/css/nexus.css` → dans `public/css/`
   - `public/js/nexus.js`  → dans `public/js/`
   - `routes/web-snippet.php` → fichier d'exemple à ouvrir / copier

2. **Ajouter les routes** dans `routes/web.php` de votre projet :

   Ouvrez `routes/web.php` et ajoutez à la fin :

   ```php
   use App\Http\Controllers\NexusFormController;

   Route::get('/nexus', [NexusFormController::class, 'showForm'])->name('nexus.form');
   Route::post('/nexus/intention', [NexusFormController::class, 'analyzeIntention'])->name('nexus.intention');
   Route::post('/nexus/submit', [NexusFormController::class, 'submit'])->name('nexus.submit');
   ```

3. **Lancer le serveur Laravel** :

   ```bash
   php artisan serve
   ```

4. Ouvrez votre navigateur sur :  
   👉 `http://localhost:8000/nexus`

   Vous verrez la page du **formulaire augmenté**.

---

## 3. Rôle de chaque partie

### 3.1 Contrôleur : `NexusFormController`

- `showForm()`
  - Renvoie la vue `nexus.index` (page principale).

- `analyzeIntention(Request $request)`
  - Reçoit soit :
    - un texte libre `text` ;
    - soit une `missionOverride` (quand l'utilisateur choisit directement la mission).
  - Si `missionOverride` existe → c'est la mission utilisée.
  - Sinon → appelle `detectMissionFromText($text)` pour détecter la mission à partir des mots-clés.
  - Récupère les champs adaptés via `getFieldsForMission($mission)`.
  - Renvoie une réponse JSON :
    ```json
    {
      "mission": "don",
      "fields": [ ... ]
    }
    ```

- `submit(Request $request)`
  - Reçoit :
    - `mission`
    - `data` (tous les champs du formulaire sous forme de tableau associatif)
  - Vérifie :
    - champ **honeypot** `website` (doit être vide) ;
    - champ `captcha_answer` doit égaler `"7"`.
  - Appelle `generateConfirmationMessage($mission, $data)` pour créer le texte de confirmation.
  - Renvoie un JSON :
    ```json
    {
      "ok": true,
      "message": "Texte personnalisé..."
    }
    ```

- `detectMissionFromText(string $text): string`
  - Version actuelle : simple détection par **mots-clés** (don, bénévolat, infos, contact).
  - ✔️ Vous pouvez remplacer son contenu par un **appel IA réel** (ex : OpenAI).

- `getFieldsForMission(string $mission): array`
  - Renvoie un tableau de définitions de champs pour chaque mission :
    - `don` → nom, email, montant, type de don, projet soutenu
    - `benevolat` → nom, email, type de mission, disponibilités, compétences
    - `infos` → nom (optionnel), email, sujet, question
    - `contact` → nom, email, sujet, message

- `generateConfirmationMessage(?string $mission, array $data): string`
  - Crée un message de confirmation :
    - intègre le **nom** (`$data['nom']` si présent) ;
    - la **mission** (don, bénévolat, infos, contact) ;
    - l'**année courante** (`now()->year`) ;
    - des références au **village numérique résistant**, à la **dépendance aux Big Tech** et à la **démarche NIRD** ;
    - propose un ton chaleureux avec emojis, en cohérence avec l'univers du défi.

  ✔️ Vous pouvez ici aussi brancher une IA pour générer le texte final.

---

### 3.2 Vue Blade : `resources/views/nexus/index.blade.php`

Cette vue contient :

- Une **intro** liée au thème :
  - “village numérique résistant” ;
  - démarche NIRD ;
  - mention de l'année `{{ now()->year }}`.
- Une section **Intention** :
  - zone de texte libre ;
  - select pour choisir directement la mission ;
  - boutons :
    - “Laisser l'IA analyser mon intention”
    - “Utiliser directement ma mission”
- Une section **Formulaire dynamique** :
  - un `<div id="dynamicFields"></div>` qui sera rempli par JS selon la mission.
  - honeypot `website` (caché, anti-spam)
  - captcha “3+4 ?”
  - bouton “Envoyer ma mission”
- Une section **Confirmation** :
  - cachée au départ (`hidden`)
  - affichée après la réponse du backend
  - montre le texte de confirmation renvoyé par le contrôleur.

La vue définit aussi :
- `window.csrfToken` pour les requêtes fetch (CSRF Laravel)
- `window.nexusRoutes` pour les URLs des routes AJAX (`intention`, `submit`)
- inclut les fichiers :
  - `public/css/nexus.css`
  - `public/js/nexus.js`

---

### 3.3 JavaScript front : `public/js/nexus.js`

- Gère les boutons :

  - `analyzeBtn` :
    - appelle `callIntentionAPI({ text: userText.value })`

  - `useMissionBtn` :
    - appelle `callIntentionAPI({ missionOverride: missionSelect.value })`

- `callIntentionAPI(options)` :
  - envoie une requête `POST` JSON vers `window.nexusRoutes.intention` avec :
    - `text`
    - `missionOverride`
  - reçoit `{ mission, fields }`
  - met à jour `currentMission`
  - reconstruit les champs dans `#dynamicFields`

- Gestion du formulaire dynamique :
  - `createField(fieldDef)` crée les inputs en fonction du type (`text`, `email`, `textarea`, `select`, `radio`).
  - `buildFormFromDefinition(fields)` pose tous les champs dans la page.

- Soumission du formulaire :
  - vérifie les champs obligatoires côté front ;
  - inclut la réponse du captcha ;
  - envoie via `fetch(window.nexusRoutes.submit)` en `POST` JSON :
    ```json
    {
      "mission": "don",
      "data": { ...tous les champs... }
    }
    ```
  - gère les erreurs (captcha, honeypot, etc.) ;
  - affiche le texte de confirmation.

---

## 4. Comment brancher un vrai endpoint IA

Vous pouvez intervenir à deux niveaux dans `NexusFormController` :

### 4.1 Pour analyser l'intention (choix de la mission)

Dans `detectMissionFromText`, remplacez le code actuel par un appel vers votre IA :

```php
private function detectMissionFromText(string $text): string
{
    // Exemple (pseudo-code) avec Http client Laravel :
    // $response = Http::withToken(config('services.openai.key'))
    //     ->post('https://api.openai.com/v1/chat/completions', [
    //         'model' => 'gpt-4.1-mini',
    //         'messages' => [
    //             ['role' => 'system', 'content' => 'Tu classes les phrases en : contact, don, benevolat, infos.'],
    //             ['role' => 'user', 'content' => $text],
    //         ],
    //     ]);
    //
    // $mission = extraire_mission_depuis_reponse($response->json());
    // return $mission;

    // Version par défaut sans appel externe :
    $t = mb_strtolower($text);
    // ... (logique actuelle)
}
```

### 4.2 Pour générer le message de confirmation

Dans `generateConfirmationMessage`, vous pouvez aussi :

- construire un `prompt` avec :
  - mission,
  - données (`$data`),
  - `$year`,
  - rappel du thème (village numérique résistant, NIRD, Big Tech),
- envoyer ce prompt à votre IA,
- retourner le texte généré.

---

## 5. Conformité au défi SFEIR

Ce module répond aux éléments attendus :

- **Formulaire d'Interaction Dynamique** :
  - mission choisie → champs qui s'adaptent ;
  - prise en charge de 4 missions (contact, don, bénévolat, infos).

- **Thème de la Nuit de l'Info 2025** :
  - mention du village numérique résistant ;
  - démarche NIRD ;
  - réduction de la dépendance aux Big Tech ;
  - année courante mentionnée dans les messages.

- **Innovation / AI** :
  - structure prête pour brancher un endpoint IA ;
  - pseudo-IA déjà en place (mots-clés) ;
  - possibilité de laisser l'IA analyser une phrase libre.

- **Accessibilité & UX** :
  - labels associés aux champs ;
  - contrastes corrects ;
  - messages dynamiques avec `aria-live` ;
  - page responsive.

---

Bonne intégration, et bon courage pour la Nuit de l'Info 2025 ✨
