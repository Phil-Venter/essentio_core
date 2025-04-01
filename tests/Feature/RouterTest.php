<?php

use Essentio\Core\HttpException;
use Essentio\Core\Request;
use Essentio\Core\Router;

describe(Router::class, function () {
    it("dispatches a static route successfully", function () {
        $router = new Router();
        $router->add("GET", "home", function ($req, $res) {
            return $res->withBody("Welcome Home");
        });

        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/home",
        ];
        $request = Request::init($server);
        $response = $router->dispatch($request);
        expect($response->body)->toBe("Welcome Home");
    });

    it("dispatches a dynamic route and extracts parameters", function () {
        $router = new Router();
        $router->add("GET", "user/:id", function ($req, $res) {
            return $res->withBody("User " . $req->get("id"));
        });

        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/user/42",
        ];
        $request = Request::init($server);
        $response = $router->dispatch($request);
        expect($response->body)->toBe("User 42");
    });

    it("throws a 404 HttpException for a non-existent route", function () {
        $router = new Router();
        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/nonexistent",
        ];
        $request = Request::init($server);
        expect(fn() => $router->dispatch($request))->toThrow(HttpException::class, "Route not found");
    });

    it("throws a 405 HttpException when the method is not allowed", function () {
        $router = new Router();
        $router->add("GET", ":about", function ($req, $res) {
            return $res->withBody("About");
        });
        $server = [
            "REQUEST_METHOD" => "POST",
            "REQUEST_URI" => "/about",
        ];
        $request = Request::init($server);
        expect(fn() => $router->dispatch($request))->toThrow(HttpException::class, "Method not allowed");
    });

    it("executes middleware pipeline correctly for a static route", function () {
        $router = new Router();
        $middleware = function ($req, $res, $next) {
            $res = $next($req, $res);
            return $res->withBody($res->body . " with middleware");
        };
        $router->add(
            "GET",
            "test",
            function ($req, $res) {
                return $res->withBody("Base");
            },
            [$middleware]
        );

        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/test",
        ];
        $request = Request::init($server);
        $response = $router->dispatch($request);
        expect($response->body)->toBe("Base with middleware");
    });

    it("dispatches a dynamic route with multiple parameters", function () {
        $router = new Router();
        $router->add("GET", "post/:postId/comment/:commentId", function ($req, $res) {
            return $res->withBody("Post " . $req->get("postId") . ", Comment " . $req->get("commentId"));
        });

        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/post/10/comment/99",
        ];
        $request = Request::init($server);
        $response = $router->dispatch($request);
        expect($response->body)->toBe("Post 10, Comment 99");
    });

    it("executes multiple middleware in the correct order", function () {
        $router = new Router();
        $middleware1 = function ($req, $res, $next) {
            $res = $next($req, $res);
            return $res->withBody($res->body . " first");
        };
        $middleware2 = function ($req, $res, $next) {
            $res = $next($req, $res);
            return $res->withBody($res->body . " second");
        };
        $router->add(
            "GET",
            "chain",
            function ($req, $res) {
                return $res->withBody("Start");
            },
            [$middleware1, $middleware2]
        );

        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/chain",
        ];
        $request = Request::init($server);
        $response = $router->dispatch($request);
        expect($response->body)->toBe("Start second first");
    });
});
