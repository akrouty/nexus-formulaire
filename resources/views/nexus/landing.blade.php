<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présentation – Le Nexus Connecté</title>
    <link rel="stylesheet" href="{{ asset('css/nexus.css') }}">
</head>

<body>
    <main class="page">
        <header class="hero" role="banner">
            <h1>Le Nexus Connecté</h1>
            <p class="subtitle">
                Bienvenue dans notre <strong>village numérique résistant</strong> 🏘️ !
            </p>
            <p class="subtitle small">
                Ce site a été créé pour le défi <strong>SFEIR – Formulaire augmenté</strong> de la
                <strong>Nuit de l'Info 2025</strong>.
            </p>
        </header>

        <section class="card">
            <h2>Pourquoi ce projet ?</h2>
            <p>
                Les établissements scolaires dépendent souvent des grandes plateformes (Big Tech)
                pour leur numérique : systèmes propriétaires, obsolescence du matériel, licences chères…
            </p>
            <p>
                Avec la démarche <strong>NIRD</strong> (Numérique Inclusif, Responsable et Durable),
                nous voulons imaginer un <strong>village numérique résistant</strong> qui :
            </p>
            <ul>
                <li>réduit sa dépendance aux Big Tech,</li>
                <li>privilégie les logiciels libres et le réemploi du matériel,</li>
                <li>donne plus d’autonomie aux écoles et aux communautés.</li>
            </ul>
        </section>

        <section class="card">
            <h2>Le formulaire augmenté</h2>
            <p>
                Dans la prochaine page, tu trouveras un <strong>formulaire d'interaction dynamique</strong>
                qui s'adapte à ton intention :
            </p>
            <ul>
                <li>Établir le contact 📞</li>
                <li>Offrir un don 💰</li>
                <li>Rejoindre la guilde des bénévoles 🛡️</li>
                <li>Demander des informations ❓</li>
            </ul>
            <p>
                L’IA nous aide à comprendre ce que tu veux faire et à personnaliser
                à la fois le formulaire et le message de remerciement, en lien avec
                l’année <strong>{{ now()->year }}</strong> et le thème de la Nuit de l’Info 2025.
            </p>

            <div class="buttons" style="margin-top:1rem;">
                <a href="{{ route('nexus.form') }}" class="btn primary" id="startBtn">
                    Commencer 🌟
                </a>
            </div>
        </section>

        <footer class="footer">
            <p>
                Défi SFEIR - Formulaire augmenté • Nuit de l'Info 2025 •
                Village numérique résistant &amp; démarche NIRD 🌱
            </p>
        </footer>
    </main>
    <div id="pageLoader" class="loader-overlay show">
        <div class="loader-circle"></div>
        <p class="loader-text">Connexion au Nexus... ✨</p>
    </div>


</body>

</html>
<script>
(function() {
    const loader = document.getElementById('pageLoader');
    const startBtn = document.getElementById('startBtn');

    if (!loader) return;


    function hideInitialLoader() {

        setTimeout(() => {
            loader.classList.remove('show');
        }, 800);
    }

    document.addEventListener('DOMContentLoaded', hideInitialLoader);


    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {

            hideInitialLoader();
        }
    });


    if (startBtn) {
        startBtn.addEventListener('click', function() {
            loader.classList.add('show');

        });
    }
})();
</script>