import { ReactNode, useMemo, useState } from 'react';
import { motion } from 'framer-motion';

interface FlipboardPageProps {
  children: ReactNode;
  className?: string;
  style?: React.CSSProperties;
}

export function FlipboardPage({
  children,
  className = '',
  style = {},
}: FlipboardPageProps) {
  return (
    <motion.div
      className={`w-full h-full flex flex-col ${className}`}
      style={{
        transformStyle: 'preserve-3d',
        ...style,
      }}
    >
      {children}
    </motion.div>
  );
}

interface FlipboardPropertyCardProps {
  property: {
    id: number;
    titulo: string;
    tipo_imovel: string;
    valor_venda?: number;
    valor_aluguel?: number;
    bairro: string;
    cidade: string;
    area_total?: number;
    area_util?: number;
    quartos?: number;
    dormitorios?: number;
    banheiros?: number;
    garagem?: number;
    descricao?: string;
    finalidade_imovel?: string;
    imagem_destaque?: string;
    fotos?: Array<{ url: string; destaque: boolean }>;
    imagens?: string[];
    images?: string[];
    photos?: string[];
  };
  primary?: string;
  onContactClick?: () => void;
}

export function FlipboardPropertyCard({
  property,
  primary = '#001775',
  onContactClick,
}: FlipboardPropertyCardProps) {
  const price = property.valor_venda || property.valor_aluguel || 0;
  const bedrooms = property.quartos ?? property.dormitorios;
  const area = property.area_util || property.area_total;
  const [imageIndex, setImageIndex] = useState(0);
  const images = useMemo(() => {
    const list: string[] = [];
    if (property.imagem_destaque) list.push(property.imagem_destaque);
    if (property.fotos?.length) list.push(...property.fotos.map((f) => f.url));
    if (property.imagens?.length) list.push(...property.imagens);
    if (property.images?.length) list.push(...property.images);
    if (property.photos?.length) list.push(...property.photos);
    const unique = Array.from(new Set(list.filter(Boolean)));
    return unique.length > 0 ? unique : ['/placeholder.jpg'];
  }, [property]);
  const image = images[Math.min(imageIndex, images.length - 1)];

  const formatPrice = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      maximumFractionDigits: 0,
    }).format(value);
  };

  return (
    <div className="w-full h-full flex flex-col bg-card text-card-foreground overflow-hidden">
      {/* Image section - 60% */}
      <div className="flex-1 relative overflow-hidden bg-muted">
        <motion.img
          key={image}
          src={image}
          alt={property.titulo}
          className="w-full h-full object-cover"
          initial={{ opacity: 0.7 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.25 }}
        />
        {/* Overlay gradient */}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />

        {images.length > 1 && (
          <>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                setImageIndex((prev) => (prev - 1 + images.length) % images.length);
              }}
              className="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/45 text-white text-lg"
              aria-label="Foto anterior"
            >
              ‹
            </button>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                setImageIndex((prev) => (prev + 1) % images.length);
              }}
              className="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/45 text-white text-lg"
              aria-label="Próxima foto"
            >
              ›
            </button>
            <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
              {images.slice(0, 6).map((_, idx) => (
                <span
                  key={idx}
                  className={`w-1.5 h-1.5 rounded-full ${idx === imageIndex ? 'bg-white' : 'bg-white/45'}`}
                />
              ))}
            </div>
          </>
        )}
      </div>

      {/* Info section - 40% */}
      <div className="flex-1 p-8 flex flex-col justify-between bg-card text-card-foreground">
        {/* Title and price */}
        <div>
          <h2 className="text-3xl font-bold mb-2" style={{ color: primary }}>
            {formatPrice(price)}
          </h2>
          <p className="text-lg font-semibold text-foreground mb-1">{property.titulo}</p>
          <p className="text-sm text-muted-foreground">
            {property.bairro}, {property.cidade}
          </p>
        </div>

        {/* Details */}
        <div className="flex items-center gap-6 mb-6 py-4 border-t border-b border-border">
          {area && (
            <div className="text-center">
              <p className="text-2xl font-bold" style={{ color: primary }}>
                {area}
              </p>
              <p className="text-xs text-muted-foreground">m²</p>
            </div>
          )}
          {bedrooms && (
            <div className="text-center">
              <p className="text-2xl font-bold" style={{ color: primary }}>
                {bedrooms}
              </p>
              <p className="text-xs text-muted-foreground">
                quarto{bedrooms > 1 ? 's' : ''}
              </p>
            </div>
          )}
        </div>

        <div className="text-xs text-muted-foreground -mt-2 mb-4 space-y-0.5">
          {property.banheiros ? <p>{property.banheiros} banheiros</p> : null}
          {property.garagem ? <p>{property.garagem} vagas</p> : null}
          {property.descricao ? <p className="line-clamp-2">{property.descricao}</p> : null}
        </div>

        {/* CTA Button */}
        <motion.button
          onClick={onContactClick}
          className="w-full py-3 px-6 rounded-xl font-bold text-white transition-all"
          style={{ backgroundColor: primary }}
          whileHover={{ scale: 1.02 }}
          whileTap={{ scale: 0.98 }}
        >
          Tenho Interesse
        </motion.button>
      </div>
    </div>
  );
}
