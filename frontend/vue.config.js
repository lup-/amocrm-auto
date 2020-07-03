module.exports = {
    "pages": {
        "admin": {
            "entry": "src/admin.js",
            "template": "templates/default.html",
            "filename": "admin.html",
            "title": "Кабинет администратора"
        },
    },
    "configureWebpack": {
        "devtool": 'eval-source-map',
        "optimization": {
            "splitChunks": false
        }
    }
}