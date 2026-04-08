<?php

declare(strict_types=1);

use App\Foundation\Controller\Admin\ConfigAdminController;
use App\Foundation\Service\ConfigService;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

function makeConfigAdminStubView(): object
{
    return new class implements \Marko\View\ViewInterface {
        public string $lastTemplate = '';
        public array $lastData = [];

        public function render(string $template, array $data = []): Response
        {
            $this->lastTemplate = $template;
            $this->lastData = $data;
            return Response::html('<html>stub</html>');
        }

        public function renderToString(string $template, array $data = []): string
        {
            return '<html>stub</html>';
        }
    };
}

it('renders the config page with current values', function () {
    $view = makeConfigAdminStubView();

    $configService = Mockery::mock(ConfigService::class);
    $configService->shouldReceive('get')->with('character_slot_default', 50)->andReturn(50);
    $configService->shouldReceive('get')->with('maintenance_mode', false)->andReturn(false);

    $controller = new ConfigAdminController($view, $configService);
    $response = $controller->index();

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::admin/config/index')
        ->and($view->lastData['config']['character_slot_default'])->toBe(50)
        ->and($view->lastData['config']['maintenance_mode'])->toBeFalse();
});

it('updates config values', function () {
    $configService = Mockery::mock(ConfigService::class);
    $configService->shouldReceive('set')->with('character_slot_default', 75)->once();
    $configService->shouldReceive('set')->with('maintenance_mode', true)->once();

    $controller = new ConfigAdminController(makeConfigAdminStubView(), $configService);

    $request = new Request(post: [
        'character_slot_default' => '75',
        'maintenance_mode' => '1',
    ]);

    $response = $controller->update($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/admin/config');
});
