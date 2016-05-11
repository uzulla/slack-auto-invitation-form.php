<?php
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config.php";

$app = new \Slim\App;

session_start();
$app->add(new \Slim\Csrf\Guard);

$container = $app->getContainer();
$container['view'] = function (\Slim\Container $container) {
    $view = new \Slim\Views\Twig(__DIR__ . '/../templates');
    $view->addExtension(new \Slim\Views\TwigExtension(
        $container['router'],
        $container['request']->getUri()
    ));
    return $view;
};

$container['errorHandler'] = function ($container) {
    return function ($request, $response, $exception) use ($container) {
        return $container->view->render($response, 'error.twig', [
            'error_message' => $exception->getMessage()
        ]);
    };
};

$app->get('/', function (\Psr\Http\Message\ServerRequestInterface $request, $response, $args) {
    $last_error = (isset($_SESSION['LAST_ERROR'])) ? $_SESSION['LAST_ERROR'] : '';
    unset($_SESSION['LAST_ERROR']);

    $team_info = get_team_info();

    return $this->view->render($response, 'form.twig', [
        'csrf_name' => (string)$request->getAttribute('csrf_name'),
        'csrf_value' => (string)$request->getAttribute('csrf_value'),
        'last_error' => (string)$last_error,
        'logo_url' => $team_info['icon_url'],
        'team_name' => $team_info['name']
    ]);
})->setName('form');

$app->post('/submit', function (\Psr\Http\Message\ServerRequestInterface $request, $response, $args) {

    $email = (string)$request->getParsedBody()['email'];
    $first_name = (string)$request->getParsedBody()['first_name'];
    $last_name = (string)$request->getParsedBody()['last_name'];

    // validate params
    $error_list = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_list['email'] = 'invalid email format';
    }

    if (!preg_match('/\A\S{1,16}\z/u', $first_name)) {
        $error_list['first_name'] = 'first name have to be /\A\S{1,16}\z/';
    }

    if (!preg_match('/\A\S{1,16}\z/u', $last_name)) {
        $error_list['last_name'] = 'last name have to be /\A\S{1,16}\z/';
    }

    if (!empty($error_list)) {
        $team_info = get_team_info();

        return $this->view->render($response, 'form.twig', [
            'csrf_name' => (string)$request->getAttribute('csrf_name'),
            'csrf_value' => (string)$request->getAttribute('csrf_value'),
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'error_list' => $error_list,
            'logo_url' => $team_info['icon_url'],
            'team_name' => $team_info['name']
        ]);
    }

    request_invitation($email, $first_name, $last_name);

    return $response->withRedirect($this->router->pathFor('finish'));

})->setName('submit');

$app->get('/finish', function (\Psr\Http\Message\ServerRequestInterface $request, $response, $args) {
    $this->view->render($response, 'finish.twig');
})->setName('finish');

$app->run();

//========================

function get_team_info()
{
    $api_response = call_api('/api/team.info', []);

    if ($api_response->ok !== true) {
        error_log(print_r($api_response, 1));
        throw new \Exception('Slack API response not ok'); // TODO more nice Exception
    }

    return [
        'name' => (string)$api_response->team->name,
        'icon_url' => (string)$api_response->team->icon->image_132
    ];
}

function request_invitation($email, $first_name, $last_name)
{
    // users.admin.invite is undocumented API, keep in mind.
    $api_response = call_api('/api/users.admin.invite?t=' . time(), [
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'set_active' => 'true',
        '_attempts' => '1',
    ]);

    if ($api_response->ok !== true) {
        throw new \Exception('Slack API response not ok, error:' . $api_response->error); // TODO more nice Exception
    }
}

function call_api($path, $params = [])
{
    $client = new \GuzzleHttp\Client([
        'base_uri' => sprintf("https://%s.slack.com/", TEAM_SUB_DOMAIN)
    ]);

    $api_response_raw = $client->request('post', $path, [
        'form_params' => array_merge(['token' => SLACK_API_TOKEN], $params)
    ]);

    $api_response = json_decode($api_response_raw->getBody());

    if ($api_response === null) {
        throw new \Exception('Slack API response an invalid json'); // TODO more nice Exception
    }

    return $api_response;
}
