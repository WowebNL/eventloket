<?php

namespace App\Http\Middleware\Api;

use App\Normalizers\OpenFormsNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeOpenformsInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($value = $request->header('X-OpenForms-Normalize')) {
            $fields = explode(',', $value);

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);

                    // param sometimes is 'None' when empty
                    if ($value === 'None' || $value === null) {
                        $request->merge([$field => null]);

                        continue;
                    }

                    $value = OpenFormsNormalizer::normalizeJson($value);

                    if (str_contains($value, '"coordinates"')) {
                        $value = OpenFormsNormalizer::normalizeGeoJson($value);
                    } elseif (preg_match('(postcode|houseNumber|houseLetter|city|streetName)', $value) === 1) {
                        $value = OpenFormsNormalizer::normalizeAddress($value);
                    }

                    $request->merge([$field => $value]);
                }
            }
        }

        return $next($request);
    }
}
