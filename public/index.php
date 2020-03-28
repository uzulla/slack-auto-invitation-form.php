<?php
// more strict error handling.
error_reporting(-1);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // error will convert exception. include notice errors.
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

require __DIR__ . "/../vendor/autoload.php";
if (strlen(getenv('SLACK_AUTO_INVITAION_SETTINGS_JSON')) > 0) {
    define('CONFIG_JSON', getenv('SLACK_AUTO_INVITAION_SETTINGS_JSON'));
} else {
    require __DIR__ . "/../config.php";
}

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
    return function ($request, $response, \Exception $exception) use ($container) {
        error_log(
            sprintf(
                "exception: %s at %s:%s\n%s",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            )
        );
        return $container->view->render($response, 'error.twig', [
            'error_message' => $exception->getMessage()
        ]);
    };
};

$app->get('/team/{team_sub_domain}', function (\Psr\Http\Message\ServerRequestInterface $request, $response, $args) {
    $team_sub_domain = $request->getAttribute('team_sub_domain');
    $team_info = get_team_info($team_sub_domain);

    return $this->view->render($response, 'form.twig', [
        'csrf_name' => (string)$request->getAttribute('csrf_name'),
        'csrf_value' => (string)$request->getAttribute('csrf_value'),
        'logo_url' => $team_info['icon_url'],
        'team_name' => $team_info['name'],
        'team_sub_domain' => $team_sub_domain
    ]);
})->setName('form');


$app->post('/team/{team_sub_domain}/submit', function (\Psr\Http\Message\ServerRequestInterface $request, $response, $args) {
    $team_sub_domain = $request->getAttribute('team_sub_domain');

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
        $team_info = get_team_info($team_sub_domain);

        return $this->view->render($response, 'form.twig', [
            'csrf_name' => (string)$request->getAttribute('csrf_name'),
            'csrf_value' => (string)$request->getAttribute('csrf_value'),
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'error_list' => $error_list,
            'logo_url' => $team_info['icon_url'],
            'team_name' => $team_info['name'],
            'team_sub_domain' => $team_sub_domain
        ]);
    }

    request_invitation($team_sub_domain, $email, $first_name, $last_name);

    return $response->withRedirect($this->router->pathFor('finish'));

})->setName('submit');

$app->get('/finish', function (\Psr\Http\Message\ServerRequestInterface $request, $response, $args) {
    $this->view->render($response, 'finish.twig');
})->setName('finish');

$app->run();

//========================

function get_team_info($team_sub_domain)
{
    $api_response = call_api($team_sub_domain, '/api/team.info');

    if ($api_response->ok !== true) {
        error_log(print_r($api_response, 1));
        throw new \Exception('Slack API response not ok'); // TODO more nice Exception
    }

    return [
        'name' => (string)$api_response->team->name,
        'icon_url' => (string)$api_response->team->icon->image_132
    ];
}

function request_invitation($team_sub_domain, $email, $first_name, $last_name)
{
    // users.admin.invite is undocumented API, keep in mind.
    $api_response = call_api($team_sub_domain, '/api/users.admin.invite?t=' . time(), [
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

function call_api($team_sub_domain, $path, $params = [])
{
    $client = new \GuzzleHttp\Client([
        'base_uri' => sprintf("https://%s.slack.com/", $team_sub_domain)
    ]);

    $api_response_raw = $client->request('post', $path, [
        'form_params' => array_merge(['token' => load_config($team_sub_domain)], $params)
    ]);

    $api_response = json_decode($api_response_raw->getBody());

    if ($api_response === null) {
        throw new \Exception('Slack API response an invalid json'); // TODO more nice Exception
    }

    return $api_response;
}

function load_config($team_sub_domain)
{
    $data = json_decode(CONFIG_JSON, true);
    if (!isset($data[$team_sub_domain])) {
        throw new \Exception('invalid team sub domain');
    }
    return $data[$team_sub_domain];
}
