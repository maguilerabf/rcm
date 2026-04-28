<?php

namespace App\Controller\Api;

use App\Message\SendCoincidenciasEmailMessage;
use App\Service\CoincidenciasExporter;
use App\Service\CoincidenciasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/identificacion-sectores')]
class CoincidenciasController extends AbstractController
{
    public function __construct(
        private readonly CoincidenciasService $coincidencias,
        private readonly CoincidenciasExporter $exporter,
        private readonly MessageBusInterface $bus,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/coincidencias', name: 'api_coincidencias_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(10, min(200, (int) $request->query->get('perPage', 50)));
        $search = $request->query->get('search') ?: null;
        $sector = $request->query->get('sector') ?: null;

        $result = $this->coincidencias->paginate($page, $perPage, $search, $sector);
        $jobs = $this->coincidencias->activeJobs();

        return new JsonResponse([
            'page' => $page,
            'perPage' => $perPage,
            'total' => $result['total'],
            'rows' => $result['rows'],
            'sectores' => $this->coincidencias->distinctSectores(),
            'jobs' => [
                'telesalud' => $jobs['telesalud']?->getId()->toRfc4122(),
                'inscritos' => $jobs['inscritos']?->getId()->toRfc4122(),
            ],
        ]);
    }

    #[Route('/coincidencias/export', name: 'api_coincidencias_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $sector = $request->query->get('sector') ?: null;
        $jobs = $this->coincidencias->activeJobs();
        if (!$jobs['telesalud'] || !$jobs['inscritos']) {
            return new JsonResponse(['error' => 'Falta cargar telesalud o inscritos.'], Response::HTTP_BAD_REQUEST);
        }

        $filename = $this->exporter->suggestFilename($sector);
        $tmpPath = sys_get_temp_dir() . '/' . $filename;

        $this->exporter->writeToPath($tmpPath, $sector);

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

    #[Route('/coincidencias/email', name: 'api_coincidencias_email', methods: ['POST'])]
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

        $jobs = $this->coincidencias->activeJobs();
        if (!$jobs['telesalud'] || !$jobs['inscritos']) {
            return new JsonResponse(['error' => 'Falta cargar telesalud o inscritos.'], Response::HTTP_BAD_REQUEST);
        }

        $this->bus->dispatch(new SendCoincidenciasEmailMessage(
            to: array_values($to),
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            body: isset($payload['body']) ? (string) $payload['body'] : null,
            sector: isset($payload['sector']) && $payload['sector'] !== '' ? (string) $payload['sector'] : null,
        ));

        return new JsonResponse(['queued' => true, 'recipients' => $to], Response::HTTP_ACCEPTED);
    }
}
