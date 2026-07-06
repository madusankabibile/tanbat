/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      colors: {
        brand: {
          50:  '#EEF0FF',
          100: '#E0E3FF',
          200: '#C5C9FF',
          300: '#9FA5FF',
          400: '#7E84FF',
          500: '#6C63FF',
          600: '#5A52D5',
          700: '#4842B0',
          800: '#36318A',
          900: '#1E1B4B',
        },
        accent: {
          400: '#FF87A1',
          500: '#FF6584',
          600: '#E54F6E',
        },
      },
      boxShadow: {
        soft: '0 6px 20px rgba(108,99,255,.12)',
        card: '0 4px 16px rgba(20, 20, 50, 0.06)',
        pop:  '0 25px 60px rgba(108,99,255,.18)',
      },
      keyframes: {
        fup: {
          '0%':   { opacity: 0, transform: 'translateY(8px)' },
          '100%': { opacity: 1, transform: 'translateY(0)' },
        },
      },
      animation: {
        fup: 'fup .25s ease forwards',
      },
    },
  },
  plugins: [],
};
