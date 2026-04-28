<?php

namespace App\Controller\Api;

use App\Message\SendDuplicadosEmailMessage;
use App\Service\DuplicadosExporter;
use App\Service\DuplicadosInscritosService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/duplicados-inscritos')]
class DuplicadosInscritosController extends AbstractController
{
    public function __construct(
        private readonly DuplicadosInscritosService $service,
        private readonly DuplicadosExporter $exporter,
        private readonly MessageBusInterface $bus,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/stats', name: 'api_duplicados_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $job = $this->service->activeJob();
        $counts = $this->service->quickCounts();
        return new JsonResponse([
            'fullGroups' => $counts['fullGroups'],
            'partialGroupsRaw' => $counts['partialGroupsRaw'],
            'job' => $job ? ['id' => $job->getId()->toRfc4122()] : null,
        ]);
    }

    #[Route('', name: 'api_duplicados_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $matchTypes = $this->parseMatchTypes($request);
        $search = $request->query->get('search') ?: null;

        $result = $this->service->detect($matchTypes, $search);
        $job = $result['job'];

        return new JsonResponse([
            'job' => $job ? [
                'id' => $job->getId()->toRfc4122(),
                'originalFilename' => $job->getOriginalFilename(),
                'finishedAt' => $job->getFinishedAt()?->format(\DateTimeInterface::ATOM),
                'rowsImported' => $job->getRowsImported(),
            ] : null,
            'groups' => $result['groups'],
            'stats' => $result['stats'],
        ]);
    }

    #[Route('/export', name: 'api_duplicados_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $matchTypes = $this->parseMatchTypes($request);
        $search = $request->query->get('search') ?: null;

        if (!$this->service->activeJob()) {
            return new JsonResponse(['error' => 'No hay padrón de inscritos cargado.'], Response::HTTP_BAD_REQUEST);
        }

        $filename = $this->exporter->suggestFilename();
        $tmpPath = sys_get_temp_dir() . '/' . $filename;
        $this->exporter->writeToPath($tmpPath, $matchTypes, $search);

        $response = new StreamedResponse(function () use ($tmpPath) {
            $stream = fopen($tmpPath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
            @unlink($tmpPath);
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Content-Length', (string) filesize($tmpPath));
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    #[Route('/email', name: 'api_duplicados_email', methods: ['POST'])]
    public function email(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $to = $payload['to'] ?? [];
        if (is_string($to)) {
            $to = array_filter(array_map('trim', explode(',', $to)));
        }
        if (!is_array($to) || empty($to)) {
            return new JsonResponse(['error' => 'Debes indicar al menos un destinatario.'], Response::HTTP_BAD_REQUEST);
        }

        $errors = [];
        $emailConstraint = new Assert\Email();
        foreach ($to as $address) {
            $violations = $this->validator->validate($address, [$emailConstraint, new Assert\NotBlank()]);
            if (count($violations) > 0) {
                $errors[] = "Correo inválido: {$address}";
            }
        }
        if ($errors) {
            return new JsonResponse(['error' => implode(' ', $errors)], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->service->activeJob()) {
            return new JsonResponse(['error' => 'No hay padrón de inscritos cargado.'], Response::HTTP_BAD_REQUEST);
        }

        $matchTypes = $this->parseMatchTypesValue($payload['matchTypes'] ?? null);
        $search = isset($payload['search']) && $payload['search'] !== '' ? (string) $payload['search'] : null;

        $this->bus->dispatch(new SendDuplicadosEmailMessage(
            to: array_values($to),
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            body: isset($payload['body']) ? (string) $payload['body'] : null,
            matchTypes: $matchTypes,
            search: $search,
        ));

        return new JsonResponse(['queued' => true, 'recipients' => $to], Response::HTTP_ACCEPTED);
    }

    /**
     * @return array<int, string>|null  null = todos
     */
    private function parseMatchTypes(Request $request): ?array
    {
        return $this->parseMatchTypesValue($request->query->get('matchTypes'));
    }

    private function parseMatchTypesValue(mixed $param): ?array
    {
        if ($param === null || $param === '' || $param === 'all') return null;
        if (is_array($param)) {
            $types = array_filter(array_map('trim', $param));
            return $types ?: null;
        }
        if (!is_string($param)) return null;
        $types = array_filter(array_map('trim', explode(',', $param)));
        return $types ?: null;
    }
}
