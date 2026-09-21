<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A generous ceiling on the encoded string, so an oversized payload is
        // rejected before it is decoded into memory. The real size check runs
        // on the decoded bytes.
        $maxEncodedChars = ((int) config('readyroute.signatures.max_kb', 512)) * 1024 * 2;

        return [
            'signature' => ['required', 'string', 'max:' . $maxEncodedChars],
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'A signature is required to sign off this trip.',
            'signature.max'      => 'That signature image is too large. Please try signing again.',
        ];
    }
}
