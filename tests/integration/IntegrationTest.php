<?php

namespace Gendoc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gendoc\Core\Application;
use Gendoc\Core\Request;
use Gendoc\Core\Response;

class IntegrationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        // Configuration de test
        $config = [
            'database' => [
                'host' => 'localhost',
                'name' => 'test_gendoc_db',
                'user' => 'test_user',
                'pass' => 'test_password',
                'charset' => 'utf8mb4'
            ],
            'app' => [
                'name' => 'Gendoc Test',
                'version' => '1.0.0',
                'debug' => true,
                'timezone' => 'Europe/Paris'
            ],
            'security' => [
                'session_timeout' => 3600,
                'password_min_length' => 8,
                'max_login_attempts' => 5
            ],
            'storage' => [
                'templates' => __DIR__ . '/../../storage/templates',
                'documents' => __DIR__ . '/../../storage/documents',
                'logs' => __DIR__ . '/../../storage/logs'
            ]
        ];

        // Note: En production, on utiliserait une base de test séparée
        // $this->app = new Application($config);
    }

    public function testApplicationInitialization()
    {
        $this->markTestSkipped('Test d\'intégration nécessite une configuration complète');
        
        // $this->assertInstanceOf(Application::class, $this->app);
    }

    public function testDatabaseConnection()
    {
        $this->markTestSkipped('Test de base de données nécessite une configuration réelle');
        
        // $database = $this->app->getDatabase();
        // $this->assertTrue($database->testConnection());
    }

    public function testSessionService()
    {
        $this->markTestSkipped('Test de session nécessite une configuration réelle');
        
        // $session = $this->app->getSession();
        // $this->assertFalse($session->isAuthenticated());
    }

    public function testLoggerService()
    {
        $this->markTestSkipped('Test de logger nécessite une configuration réelle');
        
        // $logger = $this->app->getLogger();
        // $this->assertInstanceOf(\Gendoc\Services\LoggerService::class, $logger);
    }

    public function testRequestHandling()
    {
        $this->markTestSkipped('Test de requête nécessite une configuration complète');
        
        // $_SERVER['REQUEST_METHOD'] = 'GET';
        // $_SERVER['REQUEST_URI'] = '/dashboard';
        
        // $request = new Request();
        // $this->assertEquals('GET', $request->getMethod());
        // $this->assertEquals('/dashboard', $request->getPath());
    }

    public function testResponseHandling()
    {
        $response = new Response('Test content', 200);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Test content', $response->getContent());
    }

    public function testJsonResponse()
    {
        $data = ['success' => true, 'message' => 'Test'];
        $response = Response::json($data);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=UTF-8', $response->getContentType());
        $this->assertEquals(json_encode($data), $response->getContent());
    }

    public function testRedirectResponse()
    {
        $response = Response::redirect('/dashboard');
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
    }

    public function testErrorResponse()
    {
        $response = Response::error('Test error', 500);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertTrue($response->isError());
        $this->assertEquals('Test error', $response->getContent());
    }

    public function testSuccessResponse()
    {
        $response = Response::success('Test success');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('Test success', $response->getContent());
    }

    public function testResponseHeaders()
    {
        $response = new Response('Test', 200, ['X-Test' => 'value']);
        
        $this->assertEquals('value', $response->getHeaders()['X-Test']);
    }

    public function testResponseContentType()
    {
        $response = new Response('Test');
        $response->setJson();
        
        $this->assertEquals('application/json; charset=UTF-8', $response->getContentType());
    }

    public function testResponseDownload()
    {
        $response = new Response('Test content');
        $response->setDownload('test.txt', 'text/plain');
        
        $this->assertEquals('text/plain', $response->getContentType());
        $this->assertStringContainsString('attachment; filename="test.txt"', $response->getHeaders()['Content-Disposition']);
    }

    public function testResponseCache()
    {
        $response = new Response('Test');
        $response->setCache(3600);
        
        $this->assertEquals('public, max-age=3600', $response->getHeaders()['Cache-Control']);
    }

    public function testResponseNoCache()
    {
        $response = new Response('Test');
        $response->setNoCache();
        
        $this->assertEquals('no-cache, no-store, must-revalidate', $response->getHeaders()['Cache-Control']);
        $this->assertEquals('no-cache', $response->getHeaders()['Pragma']);
        $this->assertEquals('0', $response->getHeaders()['Expires']);
    }

    public function testResponseStatusText()
    {
        $response = new Response('Test', 200);
        $this->assertEquals('OK', $response->getStatusText());
        
        $response = new Response('Test', 404);
        $this->assertEquals('Not Found', $response->getStatusText());
        
        $response = new Response('Test', 500);
        $this->assertEquals('Internal Server Error', $response->getStatusText());
    }
} 