<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gendoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.0.0/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            min-height: calc(100vh - 56px);
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, var(--success-color) 0%, #20c997 100%);
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #fd7e14 100%);
        }
        
        .stat-card.info {
            background: linear-gradient(135deg, var(--info-color) 0%, #6f42c1 100%);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .quick-action {
            text-decoration: none;
            color: inherit;
            display: block;
            padding: 1.5rem;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            color: inherit;
        }
        
        .quick-action i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .recent-documents {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .document-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }
        
        .document-item:hover {
            background-color: #f8f9fa;
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">
                <i class="fas fa-file-alt me-2"></i>Gendoc
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/profile"><i class="fas fa-user-cog me-2"></i>Profil</a></li>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="/settings"><i class="fas fa-cog me-2"></i>Paramètres</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="/dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/documents">
                                <i class="fas fa-file-alt me-2"></i>Mes Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/documents/create">
                                <i class="fas fa-plus me-2"></i>Nouveau Document
                            </a>
                        </li>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/templates">
                                    <i class="fas fa-layer-group me-2"></i>Modèles
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/admin">
                                    <i class="fas fa-users-cog me-2"></i>Administration
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshStats()">
                                <i class="fas fa-sync-alt me-1"></i>Actualiser
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <div class="stat-number"><?= $stats['total_documents'] ?></div>
                                        <div class="stat-label">Documents générés</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-alt stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card success">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <div class="stat-number"><?= $stats['this_month'] ?></div>
                                        <div class="stat-label">Ce mois</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card warning">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <div class="stat-number"><?= $stats['total_templates'] ?></div>
                                        <div class="stat-label">Modèles disponibles</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-layer-group stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card info">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <div class="stat-number"><?= $stats['storage_used'] ?>%</div>
                                        <div class="stat-label">Stockage utilisé</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hdd stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Actions rapides -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Actions rapides
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <a href="/documents/create" class="quick-action">
                                            <i class="fas fa-plus"></i>
                                            <div>Nouveau document</div>
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/documents" class="quick-action">
                                            <i class="fas fa-folder-open"></i>
                                            <div>Mes documents</div>
                                        </a>
                                    </div>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <div class="col-6 mb-3">
                                            <a href="/templates" class="quick-action">
                                                <i class="fas fa-layer-group"></i>
                                                <div>Gérer les modèles</div>
                                            </a>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <a href="/admin" class="quick-action">
                                                <i class="fas fa-users-cog"></i>
                                                <div>Administration</div>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents récents -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Documents récents
                                </h5>
                                <a href="/documents" class="btn btn-sm btn-primary">Voir tout</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="recent-documents">
                                    <?php if (empty($recent_documents)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Aucun document généré pour le moment</p>
                                            <a href="/documents/create" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Créer votre premier document
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recent_documents as $doc): ?>
                                            <div class="document-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($doc['nom']) ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?= date('d/m/Y H:i', strtotime($doc['date_gen'])) ?>
                                                        </small>
                                                    </div>
                                                    <div class="btn-group">
                                                        <a href="/documents/download/<?= $doc['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteDocument(<?= $doc['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphique d'activité -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i>Activité des 7 derniers jours
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="activityChart" width="400" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.0.0/dist/chart.min.js"></script>
    <script>
        // Graphique d'activité
        const ctx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($activity_data)) ?>,
                datasets: [{
                    label: 'Documents générés',
                    data: <?= json_encode(array_values($activity_data)) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Fonction de suppression de document
        function deleteDocument(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) {
                fetch(`/documents/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la suppression');
                });
            }
        }

        // Fonction d'actualisation des statistiques
        function refreshStats() {
            location.reload();
        }
    </script>
</body>
</html> 