const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const path = require("path");

module.exports = {
  mode: 'production',
  entry: './endereco.scss',
  output: {
    path: path.resolve(__dirname, './src/Resources/public/'),
    filename: 'endereco.css'
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          'sass-loader'
        ]
      }
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: 'endereco.min.css'
    })
  ]
};