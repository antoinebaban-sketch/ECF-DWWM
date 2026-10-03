<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Table de routage de l'API : associe "MÉTHODE /chemin/{id}" à une méthode
 * de contrôleur. Les paramètres {xxx} n'acceptent que des entiers.
 *
 * Les routes sont regroupées par ressource (premier segment de l'URL) pour
 * pouvoir renvoyer un 404 précis : "Route inconnue : xxx" si la ressource
 * n'existe pas, ou le message propre à la ressource si seule la sous-route
 * est inconnue.
 */
final class Router
{
    /** @var array<string, list<array{method: string, pattern: string, handler: array{class-string, string}}>> */
    private array $routes = [];

    /** @var array<string, array{message: string, status: int}> */
    private array $notFound = [];

    /**
     * Déclare une ressource et le message renvoyé quand aucune de ses routes
     * ne correspond (ex. "Route menus inconnue").
     */
    public function resource(string $name, string $notFoundMessage, int $status = 404): self
    {
        $this->routes[$name]   ??= [];
        $this->notFound[$name] = ['message' => $notFoundMessage, 'status' => $status];

        return $this;
    }

    /** @param array{class-string, string} $handler [Contrôleur::class, 'méthode'] */
    public function add(string $method, string $path, array $handler): self
    {
        $segments = explode('/', trim($path, '/'));
        $resource = $segments[0];
        $regex    = '#^' . preg_replace('#\{\w+\}#', '(\d+)', trim($path, '/')) . '$#';

        $this->routes[$resource][] = ['method' => $method, 'pattern' => $regex, 'handler' => $handler];

        return $this;
    }

    public function get(string $path, array $handler): self    { return $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): self   { return $this->add('POST', $path, $handler); }
    public function put(string $path, array $handler): self    { return $this->add('PUT', $path, $handler); }
    public function delete(string $path, array $handler): self { return $this->add('DELETE', $path, $handler); }

    /**
     * Trouve la route correspondant à la requête et exécute le contrôleur.
     *
     * @throws HttpException 404 / 405 si aucune route ne correspond
     */
    public function dispatch(Request $request): JsonResponse
    {
        $resource = $request->segments[0] ?? '';
        if (!isset($this->routes[$resource])) {
            throw HttpException::notFound('Route inconnue : ' . htmlspecialchars($resource));
        }

        $path = implode('/', $request->segments);
        foreach ($this->routes[$resource] as $route) {
            if ($route['method'] === $request->method && preg_match($route['pattern'], $path, $matches)) {
                [$class, $action] = $route['handler'];
                $params           = array_map('intval', array_slice($matches, 1));

                return (new $class())->$action($request, ...$params);
            }
        }

        $fallback = $this->notFound[$resource] ?? ['message' => 'Route inconnue', 'status' => 404];
        throw new HttpException($fallback['message'], $fallback['status']);
    }
}
