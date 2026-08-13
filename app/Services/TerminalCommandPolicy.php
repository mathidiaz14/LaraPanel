<?php

namespace App\Services;

class TerminalCommandPolicy
{
    public function validate(string $command): string
    {
        $command = trim($command);

        if ($command === '') {
            throw new \InvalidArgumentException('El comando no puede estar vacío.');
        }

        if (strlen($command) > 2000) {
            throw new \InvalidArgumentException('El comando supera el límite de 2000 caracteres.');
        }

        if (preg_match('/(;|\||&&|\|\||`|\$\(|\$\{|\n|\r|[<>])/', $command)) {
            throw new \InvalidArgumentException('No se permiten operadores, redirecciones ni sustituciones de shell.');
        }

        $tokens = preg_split('/\s+/', $command) ?: [];
        $base = $tokens[0] ?? '';
        if ($base === 'sudo') {
            $base = $tokens[1] ?? '';
        }

        if (! in_array($base, config('larapanel.security.allowed_terminal_commands', []), true)) {
            throw new \InvalidArgumentException("El comando '{$base}' no está permitido.");
        }

        if (preg_match('/(^|\s)(--privileged|--root|bash\s+-c|sh\s+-c)(\s|$)/i', $command)) {
            throw new \InvalidArgumentException('Ese argumento está bloqueado por seguridad.');
        }

        return $command;
    }

    public function tokens(string $command): array
    {
        return preg_split('/\s+/', trim($command)) ?: [];
    }
}
