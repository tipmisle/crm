<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Polje :attribute mora biti sprejeto.',
    'accepted_if' => 'Polje :attribute mora biti sprejeto, ko je :other :value.',
    'active_url' => 'Polje :attribute mora biti veljaven URL.',
    'after' => 'Polje :attribute mora biti datum po :date.',
    'after_or_equal' => 'Polje :attribute mora biti datum po ali enak :date.',
    'alpha' => 'Polje :attribute sme vsebovati samo črke.',
    'alpha_dash' => 'Polje :attribute sme vsebovati samo črke, številke, vezaje in podčrtaje.',
    'alpha_num' => 'Polje :attribute sme vsebovati samo črke in številke.',
    'any_of' => 'Polje :attribute ni veljavno.',
    'array' => 'Polje :attribute mora biti seznam (array).',
    'ascii' => 'Polje :attribute sme vsebovati samo enobajtne alfanumerične znake in simbole.',
    'before' => 'Polje :attribute mora biti datum pred :date.',
    'before_or_equal' => 'Polje :attribute mora biti datum pred ali enak :date.',
    'between' => [
        'array' => 'Polje :attribute mora vsebovati med :min in :max elementi.',
        'file' => 'Polje :attribute mora biti med :min in :max kilobajti.',
        'numeric' => 'Polje :attribute mora biti med :min in :max.',
        'string' => 'Polje :attribute mora vsebovati med :min in :max znaki.',
    ],
    'boolean' => 'Polje :attribute mora biti true ali false.',
    'can' => 'Polje :attribute vsebuje nepooblaščeno vrednost.',
    'confirmed' => 'Potrditev polja :attribute se ne ujema.',
    'contains' => 'Polju :attribute manjka zahtevana vrednost.',
    'current_password' => 'Geslo ni pravilno.',
    'date' => 'Polje :attribute mora biti veljaven datum.',
    'date_equals' => 'Polje :attribute mora biti datum, enak :date.',
    'date_format' => 'Polje :attribute se mora ujemati z obliko :format.',
    'decimal' => 'Polje :attribute mora imeti :decimal decimalnih mest.',
    'declined' => 'Polje :attribute mora biti zavrnjeno.',
    'declined_if' => 'Polje :attribute mora biti zavrnjeno, ko je :other :value.',
    'different' => 'Polji :attribute in :other morata biti različni.',
    'digits' => 'Polje :attribute mora imeti :digits števk.',
    'digits_between' => 'Polje :attribute mora imeti med :min in :max števkami.',
    'dimensions' => 'Polje :attribute ima neveljavne dimenzije slike.',
    'distinct' => 'Polje :attribute ima podvojeno vrednost.',
    'doesnt_contain' => 'Polje :attribute ne sme vsebovati nobene od naslednjih vrednosti: :values.',
    'doesnt_end_with' => 'Polje :attribute se ne sme končati z eno od naslednjih vrednosti: :values.',
    'doesnt_start_with' => 'Polje :attribute se ne sme začeti z eno od naslednjih vrednosti: :values.',
    'email' => 'Polje :attribute mora biti veljaven e-poštni naslov.',
    'encoding' => 'Polje :attribute mora biti kodirano v :encoding.',
    'ends_with' => 'Polje :attribute se mora končati z eno od naslednjih vrednosti: :values.',
    'enum' => 'Izbrana vrednost za :attribute ni veljavna.',
    'exists' => 'Izbrana vrednost za :attribute ni veljavna.',
    'extensions' => 'Polje :attribute mora imeti eno od naslednjih pripon: :values.',
    'file' => 'Polje :attribute mora biti datoteka.',
    'filled' => 'Polje :attribute mora imeti vrednost.',
    'gt' => [
        'array' => 'Polje :attribute mora imeti več kot :value elementov.',
        'file' => 'Polje :attribute mora biti večje od :value kilobajtov.',
        'numeric' => 'Polje :attribute mora biti večje od :value.',
        'string' => 'Polje :attribute mora imeti več kot :value znakov.',
    ],
    'gte' => [
        'array' => 'Polje :attribute mora imeti :value elementov ali več.',
        'file' => 'Polje :attribute mora biti večje ali enako :value kilobajtov.',
        'numeric' => 'Polje :attribute mora biti večje ali enako :value.',
        'string' => 'Polje :attribute mora imeti :value znakov ali več.',
    ],
    'hex_color' => 'Polje :attribute mora biti veljavna šestnajstiška barva.',
    'image' => 'Polje :attribute mora biti slika.',
    'in' => 'Izbrana vrednost za :attribute ni veljavna.',
    'in_array' => 'Polje :attribute mora obstajati v :other.',
    'in_array_keys' => 'Polje :attribute mora vsebovati vsaj enega od naslednjih ključev: :values.',
    'integer' => 'Polje :attribute mora biti celo število.',
    'ip' => 'Polje :attribute mora biti veljaven IP naslov.',
    'ipv4' => 'Polje :attribute mora biti veljaven IPv4 naslov.',
    'ipv6' => 'Polje :attribute mora biti veljaven IPv6 naslov.',
    'json' => 'Polje :attribute mora biti veljaven JSON niz.',
    'list' => 'Polje :attribute mora biti seznam.',
    'lowercase' => 'Polje :attribute mora biti zapisano z malimi črkami.',
    'lt' => [
        'array' => 'Polje :attribute mora imeti manj kot :value elementov.',
        'file' => 'Polje :attribute mora biti manjše od :value kilobajtov.',
        'numeric' => 'Polje :attribute mora biti manjše od :value.',
        'string' => 'Polje :attribute mora imeti manj kot :value znakov.',
    ],
    'lte' => [
        'array' => 'Polje :attribute ne sme imeti več kot :value elementov.',
        'file' => 'Polje :attribute mora biti manjše ali enako :value kilobajtov.',
        'numeric' => 'Polje :attribute mora biti manjše ali enako :value.',
        'string' => 'Polje :attribute mora imeti :value znakov ali manj.',
    ],
    'mac_address' => 'Polje :attribute mora biti veljaven MAC naslov.',
    'max' => [
        'array' => 'Polje :attribute ne sme imeti več kot :max elementov.',
        'file' => 'Polje :attribute ne sme biti večje od :max kilobajtov.',
        'numeric' => 'Polje :attribute ne sme biti večje od :max.',
        'string' => 'Polje :attribute ne sme imeti več kot :max znakov.',
    ],
    'max_digits' => 'Polje :attribute ne sme imeti več kot :max števk.',
    'mimes' => 'Polje :attribute mora biti datoteka vrste: :values.',
    'mimetypes' => 'Polje :attribute mora biti datoteka vrste: :values.',
    'min' => [
        'array' => 'Polje :attribute mora imeti vsaj :min elementov.',
        'file' => 'Polje :attribute mora biti vsaj :min kilobajtov.',
        'numeric' => 'Polje :attribute mora biti vsaj :min.',
        'string' => 'Polje :attribute mora imeti vsaj :min znakov.',
    ],
    'min_digits' => 'Polje :attribute mora imeti vsaj :min števk.',
    'missing' => 'Polje :attribute mora manjkati.',
    'missing_if' => 'Polje :attribute mora manjkati, ko je :other :value.',
    'missing_unless' => 'Polje :attribute mora manjkati, razen če je :other :value.',
    'missing_with' => 'Polje :attribute mora manjkati, ko je prisoten :values.',
    'missing_with_all' => 'Polje :attribute mora manjkati, ko so prisotni :values.',
    'multiple_of' => 'Polje :attribute mora biti večkratnik :value.',
    'not_in' => 'Izbrana vrednost za :attribute ni veljavna.',
    'not_regex' => 'Oblika polja :attribute ni veljavna.',
    'numeric' => 'Polje :attribute mora biti število.',
    'password' => [
        'letters' => 'Polje :attribute mora vsebovati vsaj eno črko.',
        'mixed' => 'Polje :attribute mora vsebovati vsaj eno veliko in eno malo črko.',
        'numbers' => 'Polje :attribute mora vsebovati vsaj eno številko.',
        'symbols' => 'Polje :attribute mora vsebovati vsaj en simbol.',
        'uncompromised' => 'Vneseni :attribute se je pojavil v uhajanju podatkov. Prosimo, izberite drug :attribute.',
    ],
    'present' => 'Polje :attribute mora biti prisotno.',
    'present_if' => 'Polje :attribute mora biti prisotno, ko je :other :value.',
    'present_unless' => 'Polje :attribute mora biti prisotno, razen če je :other :value.',
    'present_with' => 'Polje :attribute mora biti prisotno, ko je prisoten :values.',
    'present_with_all' => 'Polje :attribute mora biti prisotno, ko so prisotni :values.',
    'prohibited' => 'Polje :attribute je prepovedano.',
    'prohibited_if' => 'Polje :attribute je prepovedano, ko je :other :value.',
    'prohibited_if_accepted' => 'Polje :attribute je prepovedano, ko je :other sprejeto.',
    'prohibited_if_declined' => 'Polje :attribute je prepovedano, ko je :other zavrnjeno.',
    'prohibited_unless' => 'Polje :attribute je prepovedano, razen če je :other med :values.',
    'prohibits' => 'Polje :attribute preprečuje prisotnost :other.',
    'regex' => 'Oblika polja :attribute ni veljavna.',
    'required' => 'Polje :attribute je obvezno.',
    'required_array_keys' => 'Polje :attribute mora vsebovati vnose za: :values.',
    'required_if' => 'Polje :attribute je obvezno, ko je :other :value.',
    'required_if_accepted' => 'Polje :attribute je obvezno, ko je :other sprejeto.',
    'required_if_declined' => 'Polje :attribute je obvezno, ko je :other zavrnjeno.',
    'required_unless' => 'Polje :attribute je obvezno, razen če je :other med :values.',
    'required_with' => 'Polje :attribute je obvezno, ko je prisoten :values.',
    'required_with_all' => 'Polje :attribute je obvezno, ko so prisotni :values.',
    'required_without' => 'Polje :attribute je obvezno, ko :values ni prisoten.',
    'required_without_all' => 'Polje :attribute je obvezno, ko noben od :values ni prisoten.',
    'same' => 'Polje :attribute se mora ujemati z :other.',
    'size' => [
        'array' => 'Polje :attribute mora vsebovati :size elementov.',
        'file' => 'Polje :attribute mora biti :size kilobajtov.',
        'numeric' => 'Polje :attribute mora biti :size.',
        'string' => 'Polje :attribute mora vsebovati :size znakov.',
    ],
    'starts_with' => 'Polje :attribute se mora začeti z eno od naslednjih vrednosti: :values.',
    'string' => 'Polje :attribute mora biti niz znakov.',
    'timezone' => 'Polje :attribute mora biti veljaven časovni pas.',
    'unique' => 'Vrednost :attribute je že zasedena.',
    'uploaded' => 'Nalaganje :attribute ni uspelo.',
    'uppercase' => 'Polje :attribute mora biti zapisano z velikimi črkami.',
    'url' => 'Polje :attribute mora biti veljaven URL.',
    'ulid' => 'Polje :attribute mora biti veljaven ULID.',
    'uuid' => 'Polje :attribute mora biti veljaven UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'email' => 'e-pošta',
        'password' => 'geslo',
        'name' => 'ime',
    ],

];
