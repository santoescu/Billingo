export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./node_modules/preline/**/*.js",
        "./node_modules/@preline/select/**/*.js",
    ],
    theme: {
        extend: {},
    },
    plugins: [
        require('preline/plugin'),
    ],
};
