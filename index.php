<?php


require_once 'config/config.php';
require_once 'includes/functions.php';

$page_title = "Accueil - Gestion Scolaire";

try {
    require_once 'config/db.php';

    // Récupérer les dernières actualités
    $stmt = $pdo->query("SELECT * FROM actualites ORDER BY date_publication DESC LIMIT 3");
    $actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques pour la page d'accueil
    $etudiants_count = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
    $enseignants_count = $pdo->query("SELECT COUNT(*) FROM enseignants")->fetchColumn();
    $classes_count = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

} catch (PDOException $e) {
    // Mieux vaut stocker le message d'erreur dans une variable ou la session, puis afficher proprement dans la vue
    $_SESSION['error'] = "Erreur de récupération des données : " . $e->getMessage();
    // Tu peux aussi logger l'erreur dans un fichier log ici si besoin
    $actualites = [];
    $etudiants_count = 0;
    $enseignants_count = 0;
    $classes_count = 0;
}

require_once 'includes/header.php';
?>
<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

<link rel="stylesheet" href="index.css">
<!-- Hero Section avec animation de vagues -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Bienvenue à <span><?= APP_NAME ?></span></h1>
            <p class="hero-subtitle">Une plateforme complète de gestion scolaire pour les établissements sénégalais</p>
            <div class="hero-buttons">
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Connexion
                </a>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-user-plus"></i> Inscription
                </a>
            </div>
        </div>
        <div class="hero-image">
            <img src="image/bd.png" alt="M. Ndiaye">


        </div>
    </div>
    <div class="wave wave-bottom">
        <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="currentColor"></path>
            <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="currentColor"></path>
            <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="currentColor"></path>
        </svg>
    </div>
</section>

<!-- Section Statistiques -->
<section class="stats-section">
    <div class="container">
        <div class="section-header">
            <h2>Notre Établissement en Chiffres</h2>
            <p>Découvrez notre communauté éducative dynamique</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
               

                  


                <div class="stat-wave"></div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-content">
                    <h3 class="counter" data-target="100">0</h3>
                    <p>% de Réussite</p>
                </div>
                <div class="stat-wave"></div>
            </div>
        </div>
    </div>
</section>

<!-- Section Fonctionnalités avec slider -->
<section class="features-section">
    <div class="container">
        <div class="section-header">
            <h2>Nos Fonctionnalités Complètes</h2>
            <p>Tout ce dont vous avez besoin pour une gestion scolaire optimale</p>
        </div>
        
        <div class="features-slider">
            <div class="slider-container">
                <div class="slider-track">
                    <!-- Slide 1 -->
                    <div class="slide">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h3>Gestion des Étudiants</h3>
                            <p>Inscription, suivi, bulletins et historique complet de chaque étudiant.</p>
                            <div class="feature-wave"></div>
                        </div>
                    </div>
                    
                    <!-- Slide 2 -->
                    <div class="slide">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <h3>Gestion des Enseignants</h3>
                            <p>Affectation des classes et matières, suivi des heures d'enseignement.</p>
                            <div class="feature-wave"></div>
                        </div>
                    </div>
                    
                    <!-- Slide 3 -->
                    <div class="slide">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <h3>Gestion des Notes</h3>
                            <p>Saisie des notes, calcul des moyennes et génération des bulletins.</p>
                            <div class="feature-wave"></div>
                        </div>
                    </div>
                    
                    <!-- Slide 4 -->
                    <div class="slide">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3>Suivi des Absences</h3>
                            <p>Enregistrement des absences et retards avec système de justification.</p>
                            <div class="feature-wave"></div>
                        </div>
                    </div>
                    
                    <!-- Slide 5 -->
                    <div class="slide">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3>Emploi du Temps</h3>
                            <p>Génération et gestion des emplois du temps pour toutes les classes.</p>
                            <div class="feature-wave"></div>
                        </div>
                    </div>
                    
                    <!-- Slide 6 -->
                    <div class="slide">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <h3>Délibérations</h3>
                            <p>Calcul des résultats finaux et génération des procès-verbaux.</p>
                            <div class="feature-wave"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="slider-controls">
                <button class="slider-prev"><i class="fas fa-chevron-left"></i></button>
                <div class="slider-dots"></div>
                <button class="slider-next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </div>
</section>

<!-- Section Actualités -->
<section class="news-section">
    <div class="container">
        <div class="section-header">
            <h2>Dernières Actualités</h2>
            <p>Restez informé des dernières nouvelles de l'établissement</p>
        </div>
        
        <div class="news-grid">
            <?php if (!empty($actualites)): ?>
                <?php foreach ($actualites as $actualite): ?>
                    <div class="news-card">
                        <div class="news-image">
                            <img src="<?= IMG_PATH ?>news/<?= $actualite['image'] ?? 'default.jpg' ?>" alt="<?= $actualite['titre'] ?>">
                            <div class="news-date">
                                <span><?= date('d', strtotime($actualite['date_publication'])) ?></span>
                                <small><?= date('M', strtotime($actualite['date_publication'])) ?></small>
                            </div>
                        </div>
                        <div class="news-content">
                            <h3><?= $actualite['titre'] ?></h3>
                            <p><?= substr($actualite['contenu'], 0, 100) ?>...</p>
                            <a href="#" class="read-more">Lire la suite <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <div class="news-wave"></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucune actualité pour le moment.</p>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="#" class="btn btn-outline-primary">Voir toutes les actualités</a>
        </div>
    </div>
</section>

<!-- Section Témoignages -->
<section class="testimonials-section py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-4">
            <h2 class="fw-bold">Témoignages</h2>
            <p class="text-muted">Ce que disent nos étudiants et enseignants</p>
        </div>

        <div class="swiper mySwiper">
            <div class="swiper-wrapper">

                <!-- Témoignage 1 -->
                <div class="swiper-slide">
                    <div class="card shadow p-4">
                        <div class="testimonial-quote text-primary fs-3 mb-3">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p>Cette plateforme a grandement simplifié la gestion de mes classes et le suivi de mes étudiants. Un outil indispensable pour tout enseignant moderne.</p>
                        <div class="d-flex align-items-center mt-4">
                            <img src="<?= IMG_PATH ?>teachers/1.jpg" alt="Prof. Diop" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-0">Prof. Mamadou Diop</h5>
                                <small class="text-muted">Professeur de Mathématiques</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Témoignage 2 -->
                <div class="swiper-slide">
                    <div class="card shadow p-4">
                        <div class="testimonial-quote text-primary fs-3 mb-3">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p>En tant qu'administrateur, je peux maintenant gérer tout l'établissement depuis une seule plateforme. Gain de temps et efficacité garantis !</p>
                        <div class="d-flex align-items-center mt-4">
                            <img src="image/etu.png" alt="M. Ndiaye" class="rounded-circle me-3" width="60" height="60" style="border-radius: 50%;">

                            <div>
                                <h5 class="mb-0">M. Abdoulaye Ndiaye</h5>
                                <small class="text-muted">Directeur Administratif</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Témoignage 3 -->
                <div class="swiper-slide">
                    <div class="card shadow p-4">
                        <div class="testimonial-quote text-primary fs-3 mb-3">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p>Je peux consulter mes notes, mon emploi du temps et mes absences en temps réel. C'est tellement pratique et moderne !</p>
                        <div class="d-flex align-items-center mt-4">
                            <img src="<?= IMG_PATH ?>students/1.jpg" alt="A. Fall" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-0">Aminata Fall</h5>
                                <small class="text-muted">Étudiante en Terminale S</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Contrôles -->
            <div class="swiper-pagination mt-3"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
    </div>
</section>

<!-- Section Contact -->
<section class="contact-section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-info">
                <h2>Contactez-nous</h2>
                <p>Vous avez des questions ou besoin d'assistance? Notre équipe est là pour vous aider.</p>
                
                <div class="contact-methods">
                    <div class="contact-method">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Adresse</h4>
                            <p>123 Avenue Léopold Sédar Senghor, Dakar</p>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <h4>Téléphone</h4>
                            <p>+221 33 123 45 67</p>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p>contact@etablissement.sn</p>
                        </div>
                    </div>
                </div>
                
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="contact-form">
                <form id="contactForm">
                    <div class="form-group">
                        <input type="text" placeholder="Votre nom" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="email" placeholder="Votre email" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" placeholder="Sujet">
                    </div>
                    
                    <div class="form-group">
                        <textarea placeholder="Votre message" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Envoyer le message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
    const swiper = new Swiper(".mySwiper", {
        loop: true,
        spaceBetween: 30,
        slidesPerView: 1,
        pagination: {
            el: ".swiper-pagination",
            clickable: true,
        },
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        breakpoints: {
            768: {
                slidesPerView: 2,
            },
            1024: {
                slidesPerView: 3,
            }
        }
    });
</script>

<script src="script.js"></script>
<?php require_once 'includes/footer.php'; ?>

