<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur serveur - Gendoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #dc3545;
            margin: 0;
            line-height: 1;
        }
        
        .error-title {
            font-size: 1.5rem;
            color: #333;
            margin: 1rem 0;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        .error-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
            text-align: left;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1 class="error-code">500</h1>
        <h2 class="error-title">Erreur serveur</h2>
        <p class="error-message">
            Une erreur interne s'est produite. Nos équipes ont été notifiées et travaillent à résoudre le problème.
        </p>
        
        <div class="d-grid gap-2 d-md-block">
            <a href="/dashboard" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Retour au dashboard
            </a>
            <button onclick="location.reload()" class="btn btn-outline-primary">
                <i class="fas fa-redo me-2"></i>Actualiser la page
            </button>
        </div>
        
        <?php if (isset($error) && $error): ?>
        <div class="error-details">
            <strong>Détails de l'erreur :</strong><br>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <small class="text-muted">
                Si le problème persiste, contactez l'administrateur système.
            </small>
        </div>
    </div>
    
    <script>
        // Tentative de reconnexion automatique après 30 secondes
        setTimeout(function() {
            if (confirm('Voulez-vous actualiser la page pour vérifier si le problème est résolu ?')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html> 