<?php
declare(strict_types = 1);
/**
 * Created by PhpStorm.
 * User: cod
 * Date: 8.2.17
 * Time: 13:45
 */

namespace Iresults\RestImageService\Rest;


use Cundd\Rest\Dispatcher;
use Cundd\Rest\Handler\HandlerInterface;
use Cundd\Rest\Http\RestRequestInterface;
use Cundd\Rest\ResponseFactoryInterface;
use Cundd\Rest\Router\RouterInterface;
use Iresults\RestImageService\Service\ImageResizeService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class Handler implements HandlerInterface
{
    /**
     * @var \Cundd\Rest\ResponseFactoryInterface
     * @inject
     */
    private $responseFactory;

    /**
     * @var \Iresults\RestImageService\Service\ImageResizeService
     * @inject
     */
    private $imageResizeService;

    /**
     * Handler constructor.
     *
     * @param ResponseFactoryInterface $responseFactory
     * @param ImageResizeService       $imageResizeService
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ImageResizeService $imageResizeService
    ) {
        $this->responseFactory = $responseFactory;
        $this->imageResizeService = $imageResizeService;
    }

    /**
     * @param RestRequestInterface $request
     * @param string               $sizeDefinition
     * @param int                  $imageUid
     * @param string               $hash
     * @return ResponseInterface
     */
    public function serveImageWithWidthAndHeight(
        RestRequestInterface $request,
        string $sizeDefinition,
        int $imageUid,
        string $hash = ''
    ) {
        $image = $this->loadImageByUid($imageUid);
        if (!$image) {
            return $this->responseFactory->createErrorResponse('Image not found', 404, $request);
        }

        list($width, $height) = $this->prepareImageSizeDefinition($sizeDefinition);

        try {
            $uri = $this->imageResizeService->resizeImage(
                null,       // src
                $image,     // image
                $width,     // width
                $height,    // height
                null,       // minWidth
                null,       // minHeight
                null,       // maxWidth
                null,       // maxHeight
                false,      // treatIdAsReference
                null        // crop
            );

            return $this->redirect('/' . $uri);
        } catch (ResourceDoesNotExistException $exception) {
            Dispatcher::getSharedDispatcher()->logException($exception);

            return $this->responseFactory->createErrorResponse('Image file not found', 404, $request);
        } catch (\Exception $exception) {
            Dispatcher::getSharedDispatcher()->logException($exception);

            return $this->responseFactory->createErrorResponse('Error', 500, $request);
        }
    }

    /**
     * @param string $uri
     * @param int    $delay
     * @return ResponseInterface
     */
    private function redirect(string $uri, $delay = 0)
    {
        $escapedUri = htmlentities($uri, ENT_QUOTES, 'utf-8');
        $response = $this->responseFactory->createResponse(
            '<html><head><meta http-equiv="refresh" content="' . (int)$delay . ';url=' . $escapedUri . '"/></head></html>',
            303
        );

        return $response->withHeader('Location', (string)$uri);
    }

    /**
     * @param string $sizeDefinition
     * @return array
     */
    private function prepareImageSizeDefinition(string $sizeDefinition)
    {
        if (!in_array($sizeDefinition, $this->getAllowedImageSizes())) {
            throw new \UnexpectedValueException(
                sprintf('Image size definition %s is not in the list of allowed sizes', $sizeDefinition)
            );
        }

        list($width, $height) = explode('x', $sizeDefinition);

        return [
            $this->prepareImageSizeValue($width),
            $this->prepareImageSizeValue($height),
        ];
    }

    /**
     * @param $value
     * @return int|string
     */
    private function prepareImageSizeValue($value)
    {
        $value = trim($value);
        if (!$value) {
            return 0;
        }
        if (is_numeric($value)) {
            return intval($value);
        }

        $lastChar = strtolower(substr($value, -1));
        if ($lastChar === 'm' || $lastChar === 'c') {
            return intval(substr($value, 0, -1)) . $lastChar;
        }

        return 0;
    }

    /**
     * Returns a list of allowed image sizes to prevent cache flooding
     *
     * @return array
     */
    private function getAllowedImageSizes()
    {
        return [
            '200cx200c',
        ];
    }

    /**
     * Let the handler configure the routes
     *
     * @param RouterInterface      $router
     * @param RestRequestInterface $request
     */
    public function configureRoutes(RouterInterface $router, RestRequestInterface $request)
    {
        $router->routeGet(
            $request->getResourceType() . '/?',
            function () {
                return [
                    'usage' => '/rest/image_service/{dimensions}/{image_uid}',
                    'example' => '/rest/image_service/200cx200c/36',
                ];
            }
        );
        $router->routeGet(
            $request->getResourceType() . '/{slug}/{int}/{slug}/?',
            [$this, 'serveImageWithWidthAndHeight']
        );
        $router->routeGet(
            $request->getResourceType() . '/{slug}/{int}/?',
            [$this, 'serveImageWithWidthAndHeight']
        );
    }

    /**
     * Tries to load the image with the given UID
     *
     * @param int $imageUid
     * @return \TYPO3\CMS\Core\Resource\File|null
     */
    protected function loadImageByUid(int $imageUid)
    {
        try {
            $file = ResourceFactory::getInstance()->getFileObject($imageUid);
        } catch (\Exception $exception) {
            Dispatcher::getSharedDispatcher()->logException($exception);

            return null;
        }

        if ($file->getType() !== AbstractFile::FILETYPE_IMAGE) {
            Dispatcher::getSharedDispatcher()->logException(
                new \UnexpectedValueException(
                    sprintf(
                        'File #%d is not an image but of type "%s"',
                        $imageUid,
                        $file->getMimeType()
                    )
                )
            );

            return null;
        }

        return $file;
    }
}