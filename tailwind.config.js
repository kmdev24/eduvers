import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * EduVers — Movers Institute of Technology and Education
 * Premium institutional theme: warm white / slate dark with gold accents.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    darkMode: 'class',

    // Tailwind v4 ignores `content`; sources are set with @source in app.css.
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                // Signature gold scale (500 = classic metallic gold)
                gold: {
                    50:  '#FBF8EE',
                    100: '#F5EDD2',
                    200: '#EBDAA5',
                    300: '#E0C677',
                    400: '#D9BB5C',
                    500: '#D4AF37', // primary gold
                    600: '#AA7C11', // deep gold (hover / pressed)
                    700: '#8A640F',
                    800: '#6B4E0E',
                    900: '#4D380B',
                    950: '#2E2107',
                },
                // Muted champagne gold for borders, dividers, subtle accents
                champagne: {
                    DEFAULT: '#C5A059',
                    light:   '#E3CFA3',
                    dark:    '#9C7C3C',
                },
                // Warm off-white surfaces
                ivory: {
                    DEFAULT: '#FAF8F3',
                    50:  '#FFFEFB',
                    100: '#FAF8F3',
                    200: '#F3EFE6',
                    300: '#E8E2D5',
                },
                // Deep slate for dark surfaces / sidebars
                ink: {
                    DEFAULT: '#0F172A',
                    800: '#1E293B',
                    900: '#0F172A',
                    950: '#0A0F1C',
                },
            },

            fontFamily: {
                sans:  ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
                display: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
            },

            boxShadow: {
                'soft':      '0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 16px rgba(15, 23, 42, 0.06)',
                'elevated':  '0 2px 4px rgba(15, 23, 42, 0.04), 0 12px 32px rgba(15, 23, 42, 0.10)',
                'gold':      '0 4px 20px rgba(212, 175, 55, 0.25)',
                'gold-ring': '0 0 0 1px rgba(212, 175, 55, 0.45), 0 4px 20px rgba(212, 175, 55, 0.15)',
            },

            backgroundImage: {
                'gold-gradient':  'linear-gradient(135deg, #E0C677 0%, #D4AF37 45%, #AA7C11 100%)',
                'gold-sheen':     'linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent)',
                'ink-gradient':   'linear-gradient(160deg, #1E293B 0%, #0F172A 60%, #0A0F1C 100%)',
            },

            borderRadius: {
                'xl2': '1.25rem',
            },

            letterSpacing: {
                'luxe': '0.18em',
            },
        },
    },

    plugins: [forms],
};
