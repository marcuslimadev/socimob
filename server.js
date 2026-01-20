const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 8000;
const PUBLIC_DIR = path.join(__dirname, 'public');

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    let pathname = url.pathname;

    // Default to index.html for root
    if (pathname === '/' || pathname === '') {
        pathname = '/index.html';
    }

    // Handle portal routes
    if (pathname.startsWith('/portal/')) {
        pathname = '/portal' + pathname.substring(7);
    }

    const filePath = path.join(PUBLIC_DIR, pathname);

    // Check if file exists
    fs.access(filePath, fs.constants.F_OK, (err) => {
        if (err) {
            // Try adding .html extension
            const htmlPath = filePath + '.html';
            fs.access(htmlPath, fs.constants.F_OK, (err2) => {
                if (err2) {
                    res.writeHead(404, { 'Content-Type': 'text/plain' });
                    res.end('File not found');
                    return;
                }

                serveFile(htmlPath, res);
            });
            return;
        }

        serveFile(filePath, res);
    });
});

    // Default to index.html for root
    if (pathname === '/' || pathname === '') {
        pathname = '/index.html';
    }

    // Handle portal routes
    if (pathname.startsWith('/portal/')) {
        pathname = '/portal' + pathname.substring(7);
    }

    const filePath = path.join(PUBLIC_DIR, pathname);

    // Check if file exists
    fs.access(filePath, fs.constants.F_OK, (err) => {
        if (err) {
            // Try adding .html extension
            const htmlPath = filePath + '.html';
            fs.access(htmlPath, fs.constants.F_OK, (err2) => {
                if (err2) {
                    res.writeHead(404, { 'Content-Type': 'text/plain' });
                    res.end('File not found');
                    return;
                }

                serveFile(htmlPath, res);
            });
            return;
        }

        serveFile(filePath, res);
    });
});

function serveFile(filePath, res) {
    const ext = path.extname(filePath);
    let contentType = 'text/plain';

    switch (ext) {
        case '.html':
            contentType = 'text/html';
            break;
        case '.css':
            contentType = 'text/css';
            break;
        case '.js':
            contentType = 'application/javascript';
            break;
        case '.json':
            contentType = 'application/json';
            break;
        case '.png':
            contentType = 'image/png';
            break;
        case '.jpg':
        case '.jpeg':
            contentType = 'image/jpeg';
            break;
        case '.gif':
            contentType = 'image/gif';
            break;
        case '.svg':
            contentType = 'image/svg+xml';
            break;
    }

    fs.readFile(filePath, (err, data) => {
        if (err) {
            res.writeHead(500, { 'Content-Type': 'text/plain' });
            res.end('Internal server error');
            return;
        }

        res.writeHead(200, { 'Content-Type': contentType });
        res.end(data);
    });
}

server.listen(PORT, () => {
    console.log(`========================================`);
    console.log(`  EXCLUSIVA - Portal Test Server`);
    console.log(`========================================`);
    console.log(``);
    console.log(`Server running at:`);
    console.log(`  Portal: http://127.0.0.1:${PORT}/portal/`);
    console.log(`  Index:  http://127.0.0.1:${PORT}/portal/index.html`);
    console.log(`  Property: http://127.0.0.1:${PORT}/portal/imovel.html?id=1`);
    console.log(``);
    console.log(`Press Ctrl+C to stop the server`);
    console.log(`========================================`);
});