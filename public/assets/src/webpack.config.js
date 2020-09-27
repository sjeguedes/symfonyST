// Require necessary modules
const webpack = require("webpack");
const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const UglifyJSPlugin = require("uglifyjs-webpack-plugin");
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
// https://formidable.com/blog/2018/finding-webpack-duplicates-with-inspectpack-plugin/
const { DuplicatesPlugin } = require("inspectpack/plugin");
const DashboardPlugin = require("webpack-dashboard/plugin");
// Store NODE_ENV value
const env = process.env.NODE_ENV;

let config = {
    // Current "env" or fallback to development
    mode: env || "development",
    // Entries in project  "/public-dev" directory
    entry: {
        "babel-polyfill": ["./src/js/app-babel-polyfill.js"],
        "common": ["./src/js/app-common.js"],
        "uikit": ["./src/js/app-uikit-dev.js"],
        "add-trick-deletion-with-scroll": ["./src/js/app-add-trick-deletion-with-scroll.js"],
        "add-user-deletion-with-scroll": ["./src/js/app-add-user-deletion-with-scroll.js"],
        "create-or-list-trick-comment": ["./src/js/app-create-or-list-trick-comment.js"],
        "create-or-update-trick": ["./src/js/app-create-or-update-trick.js"],
        "home-trick-list": ["./src/js/app-home.js"],
        "paginated-trick-list": ["./src/js/app-paginated-list.js"],
        "scroll-to-media-box-with-hash": ["./src/js/app-scroll-to-media-box-with-hash.js"],
        "single-trick": ["./src/js/app-single.js"],
        "sort-media-box": ["./src/js/app-sort-media-box.js"],
        "update-profile": ["./src/js/app-update-profile.js"]
    },
    // Outputs in Symfony project "/public" directory
    output: {
        path: path.resolve(__dirname, "../public/assets/js"),
        filename: "./[name].js",
        globalObject: 'this'
    },
    module: {
        rules: [{
            // From ES6 to ES5
            test: /\.js$/,
            exclude: /node_modules/,
            loader: "babel-loader"
        },
        {
            // SASS: loaders are used from right to left.
            test: /\.sa|css$/,
            loader: [MiniCssExtractPlugin.loader, "css-loader", "postcss-loader", "sass-loader"]
        }]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: "../css/[name].css",
            chunkFilename: "[id].css"
        }),
        new DuplicatesPlugin({
            // Emit compilation warning or error? (Default: `false`)
            emitErrors: false,
            // Display full duplicates information? (Default: `false`)
            verbose: false
        }),
        new DashboardPlugin()
    ],
};

module.exports = config;

if (env !== "production") {
    module.exports.devtool = "cheap-module-source-map"; // "eval-source-map" // "source-map" ...
}

if (env === "production") {
    module.exports.optimization.minimizer.push(
        // Minify JS
        new UglifyJSPlugin({
            cache: true,
            parallel: true,
            sourceMap: false,
            // Added on 20/02/2019
            // https://stackoverflow.com/questions/34239731/how-to-minimize-the-size-of-webpacks-bundle
            mangle: true,
            compress: {
                warnings: false,
                pure_getters: true,
                unsafe: true,
                unsafe_comps: true,
                screw_ie8: true,
                conditionals: true,
                unused: true,
                comparisons: true,
                sequences: true,
                dead_code: true,
                evaluate: true,
                if_return: true,
                join_vars: true
            }
        }),
        // Minify CSS
        new OptimizeCSSAssetsPlugin({})
    );
}
