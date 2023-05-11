<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidInContext;

class Guards
{
    public static function for(Event $event, ?Context $context = null): static
    {
        return new static($event, $context);
    }

    public function __construct(
        public Event $event,
        public ?Context $context = null,
    ) {
    }

    public function check(): static
    {
        return $this->authorize()->validate();
    }

    public function validate(): static
    {
        if ($this->passesValidation()) {
            return $this;
        }

        if (method_exists($this->event, 'failedValidation')) {
            $this->event->failedValidation($this->context);
        }

        throw new EventNotValidInContext();
    }

    public function authorize(): static
    {
        if ($this->passesAuthorization()) {
            return $this;
        }

        if (method_exists($this->event, 'failedAuthorization')) {
            $this->event->failedAuthorization($this->context);
        }

        throw new AuthorizationException();
    }

    protected function passesValidation(): bool
    {
        if (method_exists($this->event, 'rules')) {
            $rules = app()->call([$this->event, 'rules']);
            $factory = app()->make(ValidationFactory::class);

            $validator = $factory->make((array) $this->context, $rules);

            if ($validator->fails()) {
                return false;
            }
        }

        if (method_exists($this->event, 'validate')) {
            return false !== app()->call([$this->event, 'validate']);
        }

        return true;
    }

    protected function passesAuthorization(): bool
    {
        if (method_exists($this->event, 'authorize')) {
            $result = app()->call([$this->event, 'authorize']);

            return $result instanceof Response
                ? $result->authorize()
                : $result;
        }

        return true;
    }
}