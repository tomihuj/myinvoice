<?php

declare(strict_types=1);

namespace MyInvoice\Action\AresVies;

use MyInvoice\Http\Json;
use MyInvoice\Service\Registry\RegistryGateway;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AresLookupAction
{
    public function __construct(private readonly RegistryGateway $registry) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $ic = (string) ($body['ic'] ?? '');

        $ic = preg_replace('/\D/', '', $ic) ?? '';
        if (strlen($ic) !== 8) {
            return Json::error($response, 'invalid_ic', 'IČO musí mít 8 číslic.', 400);
        }

        $result = $this->registry->lookup($ic);
        if ($result === null) {
            return Json::error($response, 'ares_unavailable', 'ARES je dočasně nedostupný.', 503);
        }

        return Json::ok($response, $result);
    }
}
