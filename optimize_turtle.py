from PIL import Image
import os
import sys

input_path = "public/assets/turtle.png"
output_path = "public/assets/turtle_optimized.png"

print(f"Otimizando imagem: {input_path}")

# Open image
img = Image.open(input_path)
print(f"Tamanho original: {img.size}")
print(f"Modo: {img.mode}")

# Convert RGBA to RGB if needed (smaller file size)
if img.mode == 'RGBA':
    # Create a white background
    background = Image.new('RGB', img.size, (255, 255, 255))
    background.paste(img, mask=img.split()[3])  # Use alpha channel as mask
    img = background
    print("Convertido de RGBA para RGB")

# Resize to max 500px (mantém proporção)
max_size = 500
if img.width > max_size or img.height > max_size:
    img.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
    print(f"Redimensionado para: {img.size}")

# Save optimized
img.save(output_path, "PNG", optimize=True, quality=85)

# Get file sizes
original_size = os.path.getsize(input_path) / 1024 / 1024
optimized_size = os.path.getsize(output_path) / 1024 / 1024

print(f"\n✅ Otimização concluída!")
print(f"   Original: {original_size:.2f} MB")
print(f"   Otimizada: {optimized_size:.2f} MB")
print(f"   Redução: {((original_size - optimized_size) / original_size * 100):.1f}%")
print(f"\n   Arquivo salvo em: {output_path}")
