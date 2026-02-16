import { ReactNode } from 'react';
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
    quartos?: number;
    dormitorios?: number;
    imagem_destaque?: string;
    fotos?: Array<{ url: string; destaque: boolean }>;
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
  const image = property.imagem_destaque || property.fotos?.[0]?.url || '/placeholder.jpg';

  const formatPrice = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      maximumFractionDigits: 0,
    }).format(value);
  };

  return (
    <div className="w-full h-full flex flex-col bg-white overflow-hidden">
      {/* Image section - 60% */}
      <div className="flex-1 relative overflow-hidden bg-gray-200">
        <motion.img
          src={image}
          alt={property.titulo}
          className="w-full h-full object-cover"
          initial={{ scale: 1 }}
          whileHover={{ scale: 1.05 }}
          transition={{ duration: 0.6 }}
        />
        {/* Overlay gradient */}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
      </div>

      {/* Info section - 40% */}
      <div className="flex-1 p-8 flex flex-col justify-between bg-white">
        {/* Title and price */}
        <div>
          <h2 className="text-3xl font-bold mb-2" style={{ color: primary }}>
            {formatPrice(price)}
          </h2>
          <p className="text-lg font-semibold text-gray-800 mb-1">{property.titulo}</p>
          <p className="text-sm text-gray-600">
            {property.bairro}, {property.cidade}
          </p>
        </div>

        {/* Details */}
        <div className="flex items-center gap-6 mb-6 py-4 border-t border-b border-gray-200">
          {property.area_total && (
            <div className="text-center">
              <p className="text-2xl font-bold" style={{ color: primary }}>
                {property.area_total}
              </p>
              <p className="text-xs text-gray-600">m²</p>
            </div>
          )}
          {bedrooms && (
            <div className="text-center">
              <p className="text-2xl font-bold" style={{ color: primary }}>
                {bedrooms}
              </p>
              <p className="text-xs text-gray-600">
                quarto{bedrooms > 1 ? 's' : ''}
              </p>
            </div>
          )}
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
