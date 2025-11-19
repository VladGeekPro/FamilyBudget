const preset = require('./vendor/filament/support/tailwind.config.preset');

module.exports = {
    presets: [preset],
    content: [
        './vendor/filament/**/*.blade.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
