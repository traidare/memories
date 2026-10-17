<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Varun Patil <radialapps@gmail.com>
 * @author Varun Patil <radialapps@gmail.com>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Memories\Controller;

use OCA\Memories\AppInfo\Application;
use OCA\Memories\Db\EmbeddedTagsQuery;
use OCA\Memories\Exceptions;
use OCA\Memories\Util;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

final class EmbeddedTagsController extends ApiController
{
    public function __construct(
        IRequest $request,
        protected Util $util,
        protected EmbeddedTagsQuery $etq,
    ) {
        parent::__construct(Application::APPNAME, $request);
    }

    /**
     * Get tags in flat manner with optional filtering and pagination.
     */
    #[NoAdminRequired]
    public function flat(): Http\Response
    {
        return $this->util->guardEx(function () {
            // Check if user is logged in
            if (!$this->util->isLoggedIn()) {
                throw Exceptions::NotLoggedIn();
            }

            // Get query parameters
            $pattern = $this->request->getParam('pattern');
            $limit = $this->request->getParam('limit');
            $offset = $this->request->getParam('offset');

            // Validate and sanitize parameters
            $limit = null !== $limit ? max(1, min(1000, (int) $limit)) : null;
            $offset = null !== $offset ? max(0, (int) $offset) : null;
            $pattern = null !== $pattern ? (string) $pattern : null;

            // Get tags
            $tags = $this->etq->getTagsFlat($pattern, $limit, $offset);

            // Get total count for pagination
            $totalCount = null;
            if (null !== $limit || null !== $offset) {
                $totalCount = $this->etq->getTagsCount($pattern);
            }

            // Prepare response
            $response = [
                'tags' => $tags,
            ];

            if (null !== $totalCount) {
                $response['pagination'] = [
                    'total' => $totalCount,
                    'limit' => $limit,
                    'offset' => $offset ?? 0,
                ];
            }

            return new JSONResponse($response, Http::STATUS_OK);
        });
    }

    /**
     * Get tags in hierarchical structure.
     */
    #[NoAdminRequired]
    public function hierarchical(): Http\Response
    {
        return $this->util->guardEx(function () {
            // Check if user is logged in
            if (!$this->util->isLoggedIn()) {
                throw Exceptions::NotLoggedIn();
            }

            // Get query parameters
            $pattern = $this->request->getParam('pattern');
            $pattern = null !== $pattern ? (string) $pattern : null;

            // Get tags in hierarchical structure
            $tags = $this->etq->getTagsHierarchical($pattern);

            return new JSONResponse([
                'tags' => $tags,
                'structure' => 'hierarchical',
            ], Http::STATUS_OK);
        });
    }

    /**
     * Get tags count (useful for pagination info).
     */
    #[NoAdminRequired]
    public function count(): Http\Response
    {
        return $this->util->guardEx(function () {
            // Check if user is logged in
            if (!$this->util->isLoggedIn()) {
                throw Exceptions::NotLoggedIn();
            }

            // Get query parameters
            $pattern = $this->request->getParam('pattern');
            $pattern = null !== $pattern ? (string) $pattern : null;

            // Get count
            $count = $this->etq->getTagsCount($pattern);

            return new JSONResponse([
                'count' => $count,
                'pattern' => $pattern,
            ], Http::STATUS_OK);
        });
    }
}
