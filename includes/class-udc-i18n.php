<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_i18n
{
    public function __construct()
    {
        add_action('init', [$this, 'register_strings']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain(
            'user-data-collection',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    public function register_strings()
    {
        if (!function_exists('pll_register_string')) {
            return;
        }

        $strings = self::get_strings();

        foreach ($strings as $key => $string) {
            $multiline = (strlen($string) > 100) ? true : false;
            // Register string with Polylang
            pll_register_string('udc_' . $key, $string, 'UDC Contact Form', $multiline);
        }
    }

    public static function translate($string_key)
    {
        $strings = self::get_strings();

        $default_string = isset($strings[$string_key]) ? $strings[$string_key] : $string_key;

        // If Polylang function is available, use it to get the translated string
        if (function_exists('pll__')) {
            return pll__($default_string);
        }

        // Fallback to standard WordPress translation
        return __($default_string, 'user-data-collection');
    }

    public static function get_strings()
    {
        return [
            'msg_success' => 'Thank you! Your submission has been received.',
            'msg_error' => 'There was an error processing your request. Please try again.',

            'doc_1_title' => 'PRIMER DOCUMENTO: CUESTIONARIO DE SALUD',
            'label_last_name' => 'Apellido *',
            'label_first_name' => 'Nombre *',
            'label_dob' => 'Fecha de nacimiento *',
            'label_address' => 'Dirección *',
            'label_city' => 'C.P., Ciudad *',
            'label_phone' => 'Teléfono *',

            'subtitle_health' => 'CUESTIONARIO DE SALUD',
            'health_good' => 'Afirmo que me encuentro actualmente en buen estado de salud.',
            'health_treatment' => 'Afirmo que sigo un tratamiento médico o dental.',
            'health_blood' => 'Afirmo que tomo regularmente medicamentos que pueden diluir la sangre, como Sintrom o aspirina (o padezco de ampollas frecuentes).',
            'health_allergies' => 'Afirmo que soy propenso(a) a alergias, látex u otros.',
            'health_pregnant' => 'Afirmo que estoy embarazada o en periodo de lactancia.',

            'subtitle_liability' => 'Cláusula de responsabilidad',
            'liability_text' => 'Declaro ser consciente de que los menores no pueden realizarse un tatuaje/piercing sin la presencia de un padre. Acepto que Titanium Shop se somete a las reglas de higiene más estrictas: agujas estériles de un solo uso y material de esterilización según las normas y con la aprobación de EYECO.ch. Asumo la entera responsabilidad por la higiene y los cuidados de mi piercing/tatuaje fuera del salón; por lo tanto, eximo al estudio de toda responsabilidad por cualquier consecuencia debida al procedimiento (infección, rechazo, alergia, etc.). Renuncio a todas las acciones legales o penales. Reconozco que si oculto deliberadamente información relativa a mi edad o de naturaleza que ponga en peligro mi salud o la del personal, podrían emprenderse acciones legales en mi contra. *',

            'doc_2_title' => 'SEGUNDO DOCUMENTO: DESCARGO DE RESPONSABILIDAD Y CUIDADOS',
            'doc_2_subtitle' => 'TITANIUM - PIERCING LAUSANNE / DESCARGO PIERCING',
            'label_appt_date' => 'Fecha de la cita *',
            'label_appt_time' => 'Hora *',
            'label_location' => 'Ubicación del piercing *',

            'subtitle_care' => 'CUIDADOS DEL PIERCING',
            'care_1' => 'Limpieza regular: Lavarse siempre bien las manos antes de tocar el piercing.',
            'care_2' => 'Evitar productos irritantes: No utilizar alcohol ni agua oxigenada. Estos productos son demasiado agresivos y pueden retrasar la curación. No utilizar jabones perfumados; use jabones suaves y sin perfume.',
            'care_3' => 'Manipulación mínima: Evite tocar el piercing. No gire la joya excepto para limpiarla. Use ropa holgada para no irritar la zona.',
            'care_4' => 'Higiene diaria: Evite la ropa ajustada para los piercings corporales (ombligo, pezón). Use ropa de algodón para dejar respirar la zona.',
            'care_5' => 'Vigilancia de signos de infección: Enrojecimiento, dolor, calor, secreciones amarillas o verdes. Si nota estos signos, consulte a un profesional de la salud. Una infección debe ser tratada rápidamente.',
            'care_6' => 'Tiempo de curación: El tiempo de curación varía según la ubicación del piercing. Los piercings en los lóbulos de las orejas suelen tardar de 6 a 8 semanas, mientras que los piercings en el cartílago, la nariz o el ombligo pueden tardar varios meses.',

            'care_accepted' => 'He leído y comprendido las indicaciones de cuidado de mi piercing. Entiendo que en caso de no seguir estas pautas rigurosamente, el estudio queda liberado de toda responsabilidad. *',

            'submit_btn' => 'Enviar / Submit'
        ];
    }
}
