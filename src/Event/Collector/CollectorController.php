<?php
namespace TSwiackiewicz\EventsCollector\Event\Collector;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use TSwiackiewicz\EventsCollector\Controller;
use TSwiackiewicz\EventsCollector\Exception\AlreadyRegisteredException;
use TSwiackiewicz\EventsCollector\Exception\InvalidControllerDefinitionException;
use TSwiackiewicz\EventsCollector\Exception\NotRegisteredException;
use TSwiackiewicz\EventsCollector\Http\JsonErrorResponse;
use TSwiackiewicz\EventsCollector\Settings\Settings;

/**
 * Class CollectorController
 * @package TSwiackiewicz\EventsCollector\Event\Collector
 */
class CollectorController implements Controller
{
    /**
     * @var CollectorService
     */
    private $service;

    /**
     * @var CollectorFactory
     */
    private $factory;

    /**
     * @param CollectorService $service
     * @param CollectorFactory $factory
     */
    public function __construct(CollectorService $service, CollectorFactory $factory)
    {
        $this->service = $service;
        $this->factory = $factory;
    }

    /**
     * @param Settings $settings
     * @return CollectorController
     */
    public static function create(Settings $settings)
    {
        $service = CollectorService::create($settings);
        $factory = new CollectorFactory();

        return new static($service, $factory);
    }

    /**
     * @param string $method
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidControllerDefinitionException
     */
    public function invoke($method, Request $request)
    {
        switch ($method)
        {
            case 'getEventCollectors':
                return $this->getEventCollectors($request);

            case 'getEventCollector':
                return $this->getEventCollector($request);

            case 'registerEventCollector':
                return $this->registerEventCollector($request);

            case 'unregisterEventCollector':
                return $this->unregisterEventCollector($request);
        }

        throw new InvalidControllerDefinitionException('Method `' . $method . '` is not defined by ' . __CLASS__);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function getEventCollectors(Request $request)
    {
        try {

            $eventType = $request->query->get('event');
            $eventCollectors = $this->service->getEventCollectors($eventType);

            $collectors = [];
            foreach ($eventCollectors as $eventCollector) {
                $collectors[] = [
                    '_id' => $eventCollector->getId(),
                    'name' => $eventCollector->getName()
                ];
            }

        } catch (NotRegisteredException $notRegistered) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_NOT_FOUND, $notRegistered->getMessage());
        } catch (\Exception $e) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_BAD_REQUEST, $e->getMessage());
        }

        return new JsonResponse(
            $collectors,
            JsonResponse::HTTP_OK
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function getEventCollector(Request $request)
    {
        try {

            $collector = $this->service->getEventCollector(
                $request->query->get('event'),
                $request->query->get('collector')
            );

        } catch (NotRegisteredException $notRegistered) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_NOT_FOUND, $notRegistered->getMessage());
        } catch (\Exception $e) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_BAD_REQUEST, $e->getMessage());
        }

        return new JsonResponse(
            $collector->toArray(),
            JsonResponse::HTTP_OK
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function registerEventCollector(Request $request)
    {
        try {

            $collector = $this->factory->create(
                $request->request->get('event'),
                $request->getContent()
            );

            $this->service->registerEventCollector($collector);

        } catch (AlreadyRegisteredException $registered) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_CONFLICT, $registered->getMessage());
        } catch (NotRegisteredException $notRegistered) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_NOT_FOUND, $notRegistered->getMessage());
        } catch (\Exception $e) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_BAD_REQUEST, $e->getMessage());
        }

        return new JsonResponse(
            [
                '_id' => $collector->getId()
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function unregisterEventCollector(Request $request)
    {
        try {

            $this->service->unregisterEventCollector(
                $request->request->get('event'),
                $request->request->get('collector')
            );

        } catch (NotRegisteredException $notRegistered) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_NOT_FOUND, $notRegistered->getMessage());
        } catch (\Exception $e) {
            return JsonErrorResponse::createJsonResponse(JsonResponse::HTTP_BAD_REQUEST, $e->getMessage());
        }

        return new JsonResponse(
            [
                'acknowledged' => true
            ],
            JsonResponse::HTTP_OK
        );
    }
}
