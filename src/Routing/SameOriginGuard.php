<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers the routes of a panel that change something ({@see CrudRouteLoader::SAME_ORIGIN}: its live route - every
 * save, delete, action and setting of its components -, the upload of files, the layout of a dashboard) only to a
 * page of the same origin, whatever else lets the request in.
 *
 * The forms of the panel carry no CSRF token: what keeps another site from sending them with the session of the one
 * signed in is the header those requests need (X-Requested-With, which LiveComponent's script, the file fields and
 * the dashboard send): a page of another origin cannot send it without the browser asking first (CORS), and an
 * application answers that only when it allows it - a CORS configuration letting other origins send that header with
 * cookies would let them in. So the browser's own word is asked as well: a request a browser says comes from another
 * origin (the header Sec-Fetch-Site: "cross-site", "same-site" - a page of another subdomain -, or "none") is refused
 * with a 403. A request without that header (an older browser, a client that is no browser) is left to the rest.
 *
 * The live route takes only GET and POST: LiveComponent refuses a GET for a component of the method "post" (the
 * default), but not a HEAD - which would hydrate the component from the address and run its "onUpdated" hooks (a note
 * of the dashboard saves its text) without being an action. And only with the header X-Requested-With, which its
 * script sends: LiveComponent asks for it itself only since 2.36 / 3.1 - before, for the header Accept alone, which
 * a page of another site may send without the browser asking first. The tags of the child components it names
 * ("children", which LiveComponent draws again for those unchanged) are names of elements: before 2.36 / 3.1
 * LiveComponent wrote them into the answer as they came.
 */
final readonly class SameOriginGuard
{
    /** What a browser says of a request from a page of the same origin. */
    private const string SAME_ORIGIN = 'same-origin';

    /** The header of a request of a script of the page, which another origin cannot send without asking first. */
    private const string XHR = 'XMLHttpRequest';

    /** The name of an element, as LiveComponent takes the tag of a child component since 2.36 / 3.1. */
    private const string TAG = '/\A[a-zA-Z][a-zA-Z0-9-]*+\z/';

    /** The methods of LiveComponent's requests. */
    private const array LIVE_METHODS = ['GET', 'POST'];

    /** After the router (32), which sets the defaults of the route; before the firewall and LiveComponent. */
    #[AsEventListener(KernelEvents::REQUEST, priority: 31)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->attributes->getBoolean(CrudRouteLoader::SAME_ORIGIN)) {
            return;
        }

        $site = $request->headers->get('Sec-Fetch-Site');
        if ($site !== null && $site !== self::SAME_ORIGIN) {
            throw new AccessDeniedHttpException(sprintf(
                'Only a page of the same origin may send this request (Sec-Fetch-Site: %s).',
                $site,
            ));
        }

        if (!$request->attributes->getBoolean(CrudRouteLoader::LIVE)) {
            return;
        }
        if (!in_array($request->getMethod(), self::LIVE_METHODS, true)) {
            throw new MethodNotAllowedHttpException(self::LIVE_METHODS);
        }
        if ($request->headers->get('X-Requested-With') !== self::XHR) {
            throw new BadRequestHttpException('Only a component of the page may send a live request.');
        }
        foreach ($this->children($request) as $child) {
            $tag = is_array($child) ? $child['tag'] ?? null : null;
            if ($tag !== null && (!is_string($tag) || preg_match(self::TAG, $tag) !== 1)) {
                throw new BadRequestHttpException('Invalid child tag.');
            }
        }
    }

    /**
     * The child components a live request names, as LiveComponent reads them: from the address (props in the query
     * string), else from the body. What is no JSON is left to LiveComponent, which refuses it.
     *
     * @return array<array-key, mixed>
     */
    private function children(Request $request): array
    {
        if ($request->query->has('props')) {
            $children = json_decode($request->query->getString('children'), true);

            return is_array($children) ? $children : [];
        }
        $data = json_decode($request->request->getString('data'), true);
        $children = is_array($data) ? $data['children'] ?? null : null;

        return is_array($children) ? $children : [];
    }
}
