const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 8000;
const PUBLIC_DIR = path.join(__dirname, 'public');

// Mock data for properties
const mockProperties = [
    {
        id: 1,
        titulo: "Apartamento Luxuoso no Centro",
        preco: 450000,
        finalidade: "venda",
        cidade: "Belo Horizonte",
        bairro: "Centro",
        endereco: "Rua da Bahia, 123",
        quartos: 3,
        banheiros: 2,
        vagas: 1,
        area: 85,
        descricao: "Apartamento reformado com vista para a cidade, próximo ao metrô e comércio.",
        imagens: [
            { url: "https://via.placeholder.com/400x300/667eea/white?text=Apartamento+Centro" },
            { url: "https://via.placeholder.com/400x300/764ba2/white?text=Sala" },
            { url: "https://via.placeholder.com/400x300/f093fb/white?text=Quarto" }
        ]
    },
    {
        id: 2,
        titulo: "Casa com Jardim em Savassi",
        preco: 3200,
        finalidade: "aluguel",
        cidade: "Belo Horizonte",
        bairro: "Savassi",
        endereco: "Rua Pernambuco, 456",
        quartos: 4,
        banheiros: 3,
        vagas: 2,
        area: 180,
        descricao: "Casa espaçosa com jardim, 3 suítes, sala de estar e jantar integradas.",
        imagens: [
            { url: "https://via.placeholder.com/400x300/4facfe/white?text=Casa+Savassi" },
            { url: "https://via.placeholder.com/400x300/00f2fe/white?text=Jardim" },
            { url: "https://via.placeholder.com/400x300/f5576c/white?text=Cozinha" }
        ]
    },
    {
        id: 3,
        titulo: "Cobertura Duplex com Vista",
        preco: 890000,
        finalidade: "venda",
        cidade: "Belo Horizonte",
        bairro: "Lourdes",
        endereco: "Av. do Contorno, 789",
        quartos: 4,
        banheiros: 4,
        vagas: 3,
        area: 220,
        descricao: "Cobertura duplex com terraço privativo e vista panorâmica da cidade.",
        imagens: [
            { url: "https://via.placeholder.com/400x300/667eea/white?text=Cobertura+Duplex" },
            { url: "https://via.placeholder.com/400x300/764ba2/white?text=Terraço" },
            { url: "https://via.placeholder.com/400x300/f093fb/white?text=Vista+Panorâmica" }
        ]
    },
    {
        id: 4,
        titulo: "Studio Moderno no Hipercentro",
        preco: 1800,
        finalidade: "aluguel",
        cidade: "Belo Horizonte",
        bairro: "Funcionários",
        endereco: "Rua Rio de Janeiro, 321",
        quartos: 1,
        banheiros: 1,
        vagas: 0,
        area: 35,
        descricao: "Studio compacto e moderno, mobiliado, ideal para profissionais.",
        imagens: [
            { url: "https://via.placeholder.com/400x300/4facfe/white?text=Studio+Moderno" },
            { url: "https://via.placeholder.com/400x300/00f2fe/white?text=Área+de+Estar" }
        ]
    },
    {
        id: 5,
        titulo: "Terreno para Construção",
        preco: 150000,
        finalidade: "venda",
        cidade: "Contagem",
        bairro: "Eldorado",
        endereco: "Rua das Flores, 1000",
        quartos: null,
        banheiros: null,
        vagas: null,
        area: 300,
        descricao: "Terreno plano com 300m², infraestrutura completa, pronto para construir.",
        imagens: [
            { url: "https://via.placeholder.com/400x300/f5576c/white?text=Terreno+300m²" }
        ]
    },
    {
        id: 6,
        titulo: "Sala Comercial no Centro",
        preco: 2800,
        finalidade: "aluguel",
        cidade: "Belo Horizonte",
        bairro: "Centro",
        endereco: "Rua Belo Horizonte, 200",
        quartos: null,
        banheiros: 2,
        vagas: 0,
        area: 80,
        descricao: "Sala comercial com 2 banheiros, ar condicionado, próximo ao shopping.",
        imagens: [
            { url: "https://via.placeholder.com/400x300/667eea/white?text=Sala+Comercial" },
            { url: "https://via.placeholder.com/400x300/764ba2/white?text=Recepção" }
        ]
    }
];

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    let pathname = url.pathname;

    // API endpoints
    if (pathname.startsWith('/portal/')) {
        if (pathname === '/portal/imoveis') {
            // Handle properties API
            const searchParams = url.searchParams;
            let filteredProperties = [...mockProperties];

            // Apply filters
            const localizacao = searchParams.get('localizacao');
            if (localizacao) {
                filteredProperties = filteredProperties.filter(p =>
                    p.cidade.toLowerCase().includes(localizacao.toLowerCase()) ||
                    p.bairro.toLowerCase().includes(localizacao.toLowerCase())
                );
            }

            const tipo = searchParams.get('tipo');
            if (tipo) {
                filteredProperties = filteredProperties.filter(p =>
                    p.titulo.toLowerCase().includes(tipo.toLowerCase())
                );
            }

            const finalidade = searchParams.get('finalidade');
            if (finalidade) {
                filteredProperties = filteredProperties.filter(p => p.finalidade === finalidade);
            }

            const preco = searchParams.get('preco');
            if (preco) {
                if (preco === '0-200000') {
                    filteredProperties = filteredProperties.filter(p => p.preco <= 200000);
                } else if (preco === '200000-500000') {
                    filteredProperties = filteredProperties.filter(p => p.preco >= 200000 && p.preco <= 500000);
                } else if (preco === '500000-1000000') {
                    filteredProperties = filteredProperties.filter(p => p.preco >= 500000 && p.preco <= 1000000);
                } else if (preco === '1000000+') {
                    filteredProperties = filteredProperties.filter(p => p.preco >= 1000000);
                }
            }

            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({
                success: true,
                data: filteredProperties
            }));
            return;
        }

        if (pathname === '/portal/config') {
            // Handle config API
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({
                success: true,
                data: {
                    total_imoveis: mockProperties.length,
                    imoveis_ativos: mockProperties.filter(p => p.finalidade === 'venda').length,
                    total_localidades: 3,
                    clientes_satisfeitos: 1250
                }
            }));
            return;
        }

        if (pathname.startsWith('/portal/imoveis/')) {
            // Handle individual property
            const propertyId = parseInt(pathname.split('/').pop());
            const property = mockProperties.find(p => p.id === propertyId);

            if (property) {
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    data: {
                        imovel: property,
                        corretor: {
                            nome: "João Silva",
                            telefone: "(31) 99999-9999",
                            email: "joao@exclusiva.com"
                        }
                    }
                }));
            } else {
                res.writeHead(404, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: false,
                    message: 'Imóvel não encontrado'
                }));
            }
            return;
        }

        // Handle static files in portal directory
        pathname = '/portal' + pathname.substring(7);
    }

    // Default to index.html for root
    if (pathname === '/' || pathname === '') {
        pathname = '/index.html';
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