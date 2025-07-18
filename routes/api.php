<?php
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/ServiceController.php';
require_once __DIR__ . '/../controllers/CustomerController.php';
require_once __DIR__ . '/../controllers/ArticleController.php';
require_once __DIR__ . '/../controllers/TemoignageController.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middlewares/JwtMiddleware.php';
require_once __DIR__ . '/../controllers/BienController.php';
require_once __DIR__ . '/../controllers/BienImageController.php';
require_once __DIR__ . '/../controllers/CommandeBienController.php';
require_once __DIR__ . '/../controllers/PublicationController.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/ReseauxSociauxController.php';
require_once __DIR__ . '/../controllers/MessageController.php';
require_once __DIR__ . '/../controllers/RefreshTokenController.php';
require_once __DIR__ . '/../controllers/ConnexionController.php';
require_once __DIR__ . '/../controllers/InsuranceCompaniesController.php';

$router = new Router();

// =====================
// ROUTES PUBLIQUES
// =====================
$router->post('/login', 'AuthController@login');

$router->post('/logout', 'AuthController@logout');

$router->post('/register', 'UserController@store');

// Catégories clients
$router->get('/categorieclient', 'CategorieClientController@index');

// Catégories clients
$router->get('/categorieclient/{id}', 'CategorieClientController@show');

$router->get('/clientsByCat', 'CustomerController@filter');
$router->get('/clients/{id}', 'CustomerController@show');
$router->get('/temoignage', 'TemoignageController@index');
$router->get('/temoignage/client', 'TemoignageController@getTemByPagination');
$router->get('/articles', 'ArticleController@index');
$router->get('/articles/{id}', 'ArticleController@show');
$router->get('/articles/slug/{slug}', 'ArticleController@showBySlug');
$router->get('/services', 'ServiceController@index');
$router->get('/servicesBycat', 'ServiceController@filter');
$router->get('/services/{id}', 'ServiceController@show');
$router->get('/categories', 'CategoryController@index');
$router->get('/categories/{id}', 'CategoryController@show');
$router->get('/experts', 'ExpertController@index');
$router->get('/reseaux_sociaux', 'ReseauxSociauxController@index');
$router->delete('/reseaux_sociaux/{id}', 'ReseauxSociauxController@destroy');


//Point d'accee pour enregistrer le type d'assurance
$router->post('/insurance_companies', 'InsuranceCompaniesController@store');
$router->get('/insurance_companies', 'InsuranceCompaniesController@mich');


//Point d'accee  type d'assurance
$router->post('/insurance_type', 'InsuranceTypesController@store');
$router->get('/insurance_type', 'InsuranceTypesController@index');

//Point d'accee  type d'assurance
$router->post('/company_products', 'CompanyProductsController@store');
$router->get('/company_products', 'CompanyProductsController@index');


$router->get('/publications_pub', 'PublicationController@getPublicationPub');

$router->post('/contacts', 'MessageContactController@store');

// =====================
// ROUTES PROTÉGÉES (JWT)
// =====================
$router->group(['middleware' => 'JwtMiddleware'], function ($router) {
  // Users
  
$router->get('/clients', 'CustomerController@index');
  $router->get('/users', 'UserController@index');
  $router->get('/users/available-for-conversation', 'UserController@index');
  $router->get('/users/{id}', 'UserController@show');
  $router->post('/users', 'UserController@store');
  $router->put('/users/{id}', 'UserController@update');
  $router->delete('/users/{id}', 'UserController@destroy');

  // Catégories
  $router->post('/categories', 'CategoryController@store');
  $router->put('/categories/{id}', 'CategoryController@update');
  $router->delete('/categories/{id}', 'CategoryController@destroy');

  // ReseauSociaux
  $router->post('/reseaux_sociaux', 'ReseauxSociauxController@store');
  $router->put('/reseaux_sociaux/{id}', 'ReseauxSociauxController@update');

  // Experts
  $router->post('/experts', 'ExpertController@store');

  // Demandes
  $router->get('/demandes', 'DemandeController@index');
  $router->post('/demandes', 'DemandeController@store');

  // Services
  $router->post('/services', 'ServiceController@store');
  $router->put('/services/{id}', 'ServiceController@update');
  $router->delete('/del_services/{id}', 'ServiceController@destroy');

  // Clients
  $router->post('/clients', 'CustomerController@store');
  $router->put('/clients/{id}', 'CustomerController@update');
  $router->delete('/clients/{id}', 'CustomerController@destroy');

  // Articles
  $router->post('/articles', 'ArticleController@store');
  $router->put('/articles/{id}', 'ArticleController@update');
  $router->delete('/articles/{id}', 'ArticleController@destroy');

  // Témoignages
  $router->post('/temoignage', 'TemoignageController@store');
  $router->put('/temoignage/{id}', 'TemoignageController@update');
  $router->delete('/temoignage/{id}', 'TemoignageController@destroy');
  $router->post('/temoignage/upload-image', 'TemoignageController@uploadImage');


  // Biens
  $router->get('/biens', 'BienController@index');
  $router->get('/biens/{id}', 'BienController@show');
  $router->post('/biens', 'BienController@store');
  $router->put('/biens/{id}', 'BienController@update');
  $router->delete('/biens/{id}', 'BienController@destroy');
  $router->get('/user/biens', 'BienController@byUser');

  // Images biens
  $router->post('/biens/{id}/images', 'BienImageController@store');
  $router->get('/biens/{id}/images', 'BienImageController@getByBien');
  $router->delete('/biens/images/{id}', 'BienImageController@destroy');

  // Commandes
  $router->get('/commandes', 'CommandeBienController@index');
  $router->get('/commandes/{id}', 'CommandeBienController@show');
  $router->post('/commandes', 'CommandeBienController@store');
  $router->get('/user/commandes/acheteur', 'CommandeBienController@byAcheteur');
  $router->get('/user/commandes/vendeur', 'CommandeBienController@byVendeur');

  // Publications
  $router->get('/publications', 'PublicationController@index');
  $router->get('/publications/{id}', 'PublicationController@show');
  $router->get('/publications/slug/{slug}', 'PublicationController@showBySlug');
  $router->post('/publications', 'PublicationController@store');
  $router->put('/publications/{id}', 'PublicationController@update');
  $router->delete('/publications/{id}', 'PublicationController@destroy');
  $router->post('/publications/upload-image', 'PublicationController@uploadImage');

  // Profil
  $router->get('/profile', 'UserController@profile');

  // =====================
  // NOUVELLES ROUTES MESSAGERIE
  // =====================

  // Récupérer toutes les conversations de l'utilisateur
  $router->get('/conversations', 'MessageController@getUserConversations');

  // Démarrer une nouvelle conversation
  $router->post('/conversations', 'MessageController@startConversation');

  // Envoyer un message dans une conversation
  $router->post('/conversations/{conversation_id}/messages', 'MessageController@sendMessage');

  // Récupérer les messages d'une conversation
  $router->get('/conversations/{conversation_id}/messages', 'MessageController@getConversationMessages');

  // Marquer une conversation comme lue
  $router->patch('/conversations/{conversation_id}/read', 'MessageController@markAsRead');

  // Supprimer une conversation
  $router->delete('/conversations/{conversation_id}', 'MessageController@deleteConversation');

  // Supprimer un message
  $router->delete('/messages/{id}', 'MessageController::deleteMessage');

  // Restaurer un message
  $router->patch('/messages/{id}/restore', 'MessageController::restoreMessage');

  // Refresh Tokens CRUD (Admin)
  $router->get('/refresh-tokens', 'RefreshTokenController@index');
  $router->delete('/refresh-tokens/{token}', 'RefreshTokenController@delete');
  $router->post('/refresh-tokens/clean-expired', 'RefreshTokenController@cleanExpired');
  $router->post('/refresh-token', 'RefreshTokenController@refreshToken');


  // Connexions (logs de connexion)
  $router->get('/connexions', 'ConnexionController@index');
  $router->delete('/connexions/{id}', 'ConnexionController@delete');
  $router->post('/connexions/clean-old', 'ConnexionController@cleanOld');

  //Message contact
  $router->get('/contacts', 'MessageContactController@index');       
  $router->get('/contacts/{id}', 'MessageContactController@show');    
  $router->delete('/contacts/{id}', 'MessageContactController@destroy');


});

// =====================
// DISPATCH ET 404
// =====================
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

if (!$router->dispatch($requestMethod, $requestUri)) {
  http_response_code(404);
  echo json_encode(['error' => 'Route non trouvée', 'path' => $requestUri]);
}
