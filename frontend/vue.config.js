module.exports = {
    "pages": {
        "admin": {
            "entry": "src/admin.js",
            "template": "templates/default.html",
            "filename": "admin.html",
            "title": "Кабинет администратора"
        },
        "video": {
            "entry": "src/video.js",
            "template": "templates/default.html",
            "filename": "video.html",
            "title": "Уроки вождения"
        },
    },
    "configureWebpack": {
        "devtool": 'eval-source-map',
        "optimization": {
            "splitChunks": false
        }
    }
}