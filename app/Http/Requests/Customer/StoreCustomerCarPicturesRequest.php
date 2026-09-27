<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class StoreCustomerCarPicturesRequest extends BaseRequest
{
    use CustomerCarPictureRules;

    public function rules(): array
    {
        return [
            'pictures' => [
                'required',
                'array',
                'min:1',
                'max:'.self::pictureLimit(),
            ],
            'pictures.*' => self::pictureFileRules(),
        ];
    }

    public function messages(): array
    {
        return self::pictureMessages(picturesRequired: true);
    }
}
