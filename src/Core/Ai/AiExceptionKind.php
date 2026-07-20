<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Ai;

enum AiExceptionKind: string
{
    case Auth            = 'auth';
    case RateLimit       = 'rate_limit';
    case QuotaExceeded   = 'quota_exceeded';
    case Transport       = 'transport';
    case BadRequest      = 'bad_request';
    case ServerError     = 'server_error';
    case InvalidResponse = 'invalid_response';
    case PromptFiltered  = 'prompt_filtered';
    case EgressBlocked   = 'egress_blocked';
    case Unknown         = 'unknown';
}
