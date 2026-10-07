import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Kravio hanya bergaya gelap: <html class="dark"> dipasang di layout.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Kelas yang dipasang dari JS (mis. efek seret di SortableJS).
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Archivo', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Abu-abu kebiruan ruang bioskop saat lampu padam; menggantikan
                // skala gray bawaan supaya semua halaman ikut tanpa diubah satu-satu.
                gray: {
                    50: '#F4F5F7',
                    100: '#E8E9ED',
                    200: '#D3D5DC',
                    300: '#B4B8C3',
                    400: '#9095A3',
                    500: '#6B7080',
                    600: '#4A4E5E',
                    700: '#343746',
                    800: '#262833',
                    900: '#1A1C23',
                    950: '#121319',
                },
                layar: '#1A1C23',
                kursi: '#262833',
                perak: '#E8E9ED',
                redup: '#9095A3',
                // Kuning subtitle: hanya untuk kata-kata orang dan rating.
                subtitle: '#F2D649',
                kredit: '#C9473D',
            },
        },
    },

    plugins: [forms],
};
