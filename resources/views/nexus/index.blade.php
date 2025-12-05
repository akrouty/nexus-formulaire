<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Nexus Connecté - Formulaire augmenté</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/nexus.css') }}">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>


</head>

<body>
    <main class="page">
        <header class="hero" role="banner">
            <h1>Le Nexus Connecté : L'Écho Personnalisé</h1>
            <p class="subtitle">
                Aide notre <strong>village numérique résistant</strong> 🏘️ à renforcer son
                lien avec ses soutiens, tout en respectant la démarche
                <strong>NIRD</strong> (Numérique Inclusif, Responsable et Durable).
            </p>
            <p class="subtitle small">
                Ce formulaire augmenté s'adapte à ton intention (contact, don, bénévolat, demande d'infos)
                et te renvoie un message personnalisé en <strong>{{ now()->year }}</strong> ✨.
            </p>
        </header>

        <section class="card" aria-labelledby="intent-title">
            <h2 id="intent-title">1. Exprime ton intention</h2>
            <p>
                Écris en une phrase ce que tu veux faire, ou choisis directement ta mission.
                L'IA t'aidera à construire le formulaire adapté 💡.
            </p>

            <label for="userText" class="label">Ton intention </label>
            <textarea id="userText" class="input" rows="3"
                placeholder="Ex : Je voudrais faire un petit don pour soutenir les écoles."></textarea>



            <div class="buttons">
                <button id="analyzeBtn" class="btn primary">Laisser l'IA analyser mon intention</button>

            </div>
            <div id="ai-loading" class="ai-loading hidden">
                L’IA analyse ton intention… ✨
            </div>
            <p id="intentResult" class="intent-result"></p>

            <div id="mission-result" class="mission-result hidden">
                Mission détectée : <span id="mission-label"></span>
            </div>
        </section>

        <section class="card" aria-labelledby="form-title">
            <h2 id="form-title">2. Formulaire d'interaction dynamique</h2>
            <p>
                Les champs ci-dessous se mettent à jour en fonction de ta mission 🎯.
            </p>

            <form id="dynamicForm" novalidate>
                <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">

                <div id="dynamicFields"></div>

                <!-- Anti-spam honeypot (à laisser vide) -->
                <div class="honeypot" aria-hidden="true">
                    <label for="website">Laissez ce champ vide</label>
                    <input id="website" name="website" type="text" autocomplete="off">
                </div>

                <!-- Petit captcha humain -->
                <div class="g-recaptcha" data-sitekey="{{ env('RECAPTCHA_SITE_KEY') }}"></div>


                <button id="submitBtn" type="submit" class="btn primary" disabled>
                    Envoyer ma mission
                </button>

                <p id="formError" class="error" aria-live="polite"></p>
            </form>
        </section>

        {{-- Popup de confirmation --}}
        <div id="confirmationModal" class="modal hidden" role="dialog" aria-modal="true"
            aria-labelledby="confirmationTitle">
            <div class="modal-backdrop"></div>

            <div class="modal-card card">
                <h2 id="confirmationTitle">Ton écho personnalisé</h2>
                <div id="confirmationText" class="confirmation-text"></div>


                <div class="buttons" style="margin-top:1rem; justify-content:flex-end;">
                    <button id="closeModalBtn" type="button" class="btn secondary">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
        <div id="ai-loading" class="ai-loading hidden">
            L’IA réfléchit à ta mission… ✨
        </div>
        <div id="errorToast" class="toast toast-error hidden"></div>


        <footer class="footer">
            <p>
                Défi SFEIR - Formulaire augmenté • Nuit de l'Info 2025 •
                Village numérique résistant &amp; démarche NIRD 🌱
            </p>
        </footer>
    </main>

    <script>
    window.csrfToken = "{{ csrf_token() }}";
    window.nexusRoutes = {
        intention: "{{ route('nexus.intention') }}",
        submit: "{{ route('nexus.submit') }}"
    };
    </script>
    <script src="{{ asset('js/nexus.js') }}" defer></script>

</body>



</html>