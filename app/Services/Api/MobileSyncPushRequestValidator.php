<?php

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class MobileSyncPushRequestValidator
{
    /**
     * @param class-string<FormRequest> $requestClass
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $routeParameters
     */
    public function make(
        string $requestClass,
        string $method,
        array $payload,
        User $user,
        array $routeParameters = [],
    ): FormRequest {
        /** @var FormRequest $request */
        $request = $requestClass::create('/', strtoupper($method), $payload);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(static fn (): User => $user);
        $route = new class($routeParameters)
        {
            /** @param array<string, mixed> $parameters */
            public function __construct(private readonly array $parameters)
            {
            }

            public function parameter(string $key, mixed $default = null): mixed
            {
                return $this->parameters[$key] ?? $default;
            }
        };
        $request->setRouteResolver(static fn () => $route);

        return $request;
    }

    /** @throws AuthorizationException */
    public function authorize(FormRequest $request): void
    {
        if (! $request->authorize()) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /** @return array<string, mixed> */
    public function validate(FormRequest $request): array
    {
        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        if (method_exists($request, 'after')) {
            foreach ((array) $request->after() as $callback) {
                $validator->after($callback);
            }
        }

        return $validator->validate();
    }
}
