<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\SiteController;
use App\Core\Router;

$router = new Router();

$router->get('/', [SiteController::class, 'home']);
$router->get('/hosting', [SiteController::class, 'hosting']);
$router->get('/wordpress-hosting', [SiteController::class, 'wordpressHosting']);
$router->get('/website-development', [SiteController::class, 'websiteDevelopment']);
$router->get('/domains', [SiteController::class, 'domains']);
$router->get('/domain-search', [SiteController::class, 'domainSearchPage']);
$router->post('/domain-search', [SiteController::class, 'domainSearch']);
$router->get('/api/domain-search', [SiteController::class, 'apiDomainSearch']);
$router->get('/about', [SiteController::class, 'about']);
$router->get('/contact', [SiteController::class, 'contact']);
$router->post('/contact', [SiteController::class, 'submitContact']);
$router->get('/blog', [SiteController::class, 'blog']);
$router->get('/blog/{slug}', [SiteController::class, 'blogPost']);
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);
$router->get('/robots.txt', [SiteController::class, 'robots']);
$router->get('/admin/login', [AdminController::class, 'login']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->get('/admin/logout', [AdminController::class, 'logout']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->post('/admin/settings', [AdminController::class, 'settings']);
$router->get('/admin/homepage', [AdminController::class, 'homepage']);
$router->post('/admin/homepage', [AdminController::class, 'homepage']);
$router->get('/admin/package', [AdminController::class, 'package']);
$router->post('/admin/package', [AdminController::class, 'package']);

$router->get('/admin/plans', [AdminController::class, 'plans']);
$router->get('/admin/plans/create', [AdminController::class, 'planForm']);
$router->get('/admin/plans/{id}/edit', [AdminController::class, 'planForm']);
$router->post('/admin/plans/save', [AdminController::class, 'savePlan']);
$router->post('/admin/plans/{id}/delete', [AdminController::class, 'deletePlan']);

$router->get('/admin/tlds', [AdminController::class, 'tlds']);
$router->get('/admin/tlds/create', [AdminController::class, 'tldForm']);
$router->get('/admin/tlds/{id}/edit', [AdminController::class, 'tldForm']);
$router->post('/admin/tlds/save', [AdminController::class, 'saveTld']);
$router->post('/admin/tlds/{id}/delete', [AdminController::class, 'deleteTld']);

$router->get('/admin/pages', [AdminController::class, 'pages']);
$router->get('/admin/pages/{id}/edit', [AdminController::class, 'pageForm']);
$router->post('/admin/pages/save', [AdminController::class, 'savePage']);

$router->get('/admin/blog', [AdminController::class, 'posts']);
$router->get('/admin/blog/create', [AdminController::class, 'postForm']);
$router->get('/admin/blog/{id}/edit', [AdminController::class, 'postForm']);
$router->post('/admin/blog/save', [AdminController::class, 'savePost']);
$router->post('/admin/blog/{id}/delete', [AdminController::class, 'deletePost']);

$router->get('/admin/testimonials', [AdminController::class, 'testimonials']);
$router->get('/admin/testimonials/create', [AdminController::class, 'testimonialForm']);
$router->get('/admin/testimonials/{id}/edit', [AdminController::class, 'testimonialForm']);
$router->post('/admin/testimonials/save', [AdminController::class, 'saveTestimonial']);
$router->post('/admin/testimonials/{id}/delete', [AdminController::class, 'deleteTestimonial']);

$router->get('/admin/faqs', [AdminController::class, 'faqs']);
$router->get('/admin/faqs/create', [AdminController::class, 'faqForm']);
$router->get('/admin/faqs/{id}/edit', [AdminController::class, 'faqForm']);
$router->post('/admin/faqs/save', [AdminController::class, 'saveFaq']);
$router->post('/admin/faqs/{id}/delete', [AdminController::class, 'deleteFaq']);

$router->get('/admin/inquiries', [AdminController::class, 'inquiries']);
$router->post('/admin/inquiries/{id}/status', [AdminController::class, 'updateInquiry']);
$router->post('/admin/inquiries/{id}/delete', [AdminController::class, 'deleteInquiry']);

$router->get('/admin/seo', [AdminController::class, 'seo']);
$router->get('/admin/seo/{id}/edit', [AdminController::class, 'seoForm']);
$router->post('/admin/seo/save', [AdminController::class, 'saveSeo']);
$router->get('/admin/export', [AdminController::class, 'export']);

$router->get('/{slug}', [SiteController::class, 'page']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
