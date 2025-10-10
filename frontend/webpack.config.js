const path = require('path');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  // Entry point - your main JavaScript file
  entry: './public/js/main.js',
  
  // Output configuration
  output: {
    filename: 'js/bundle.js',  // Bundled JS will be in dist/js/bundle.js
    path: path.resolve(__dirname, 'dist'),
    clean: true  // Clean dist folder before each build
  },
  
  // Copy static files that don't need processing
  plugins: [
    new CopyWebpackPlugin({
      patterns: [
        // Copy HTML
        { 
          from: 'public/index.html', 
          to: 'index.html' 
        },
        // Copy all CSS files
        { 
          from: 'public/css', 
          to: 'css' 
        }
      ],
    }),
  ],
  
  // Development options
  devtool: 'source-map',  // Helps with debugging
  
  // Module rules for processing different file types
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        // You can add babel-loader here later if you want ES6+ transpilation
      }
    ]
  }
};