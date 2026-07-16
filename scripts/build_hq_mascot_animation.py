from math import pi, sin
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "client" / "public" / "assets" / "exclusiva-mascot-hq.png"
OUTPUT = ROOT / "client" / "public" / "assets" / "exclusiva-mascot-hq-preview.webp"

FRAME_COUNT = 150
TOTAL_DURATION_MS = 5000
CANVAS_WIDTH = 680

source = Image.open(SOURCE).convert("RGBA")
bounds = source.getbbox()
if not bounds:
    raise RuntimeError("A imagem do mascote está vazia")

source = source.crop(bounds)
scale = CANVAS_WIDTH / source.width
base_height = round(source.height * scale)
source = source.resize((CANVAS_WIDTH, base_height), Image.Resampling.LANCZOS)
canvas_height = base_height + 18

# Coordenadas normalizadas dos olhos na arte-mestra, após o recorte.
eye_boxes = (
    (0.405, 0.025, 0.535, 0.165),
    (0.665, 0.025, 0.800, 0.165),
)


def blink_amount(frame: int) -> float:
    amount = 0.0
    for start, length in ((38, 18), (112, 14)):
        offset = frame - start
        if 0 <= offset < length:
            amount = max(amount, sin(pi * offset / (length - 1)))
    return amount


frames: list[Image.Image] = []
for index in range(FRAME_COUNT):
    phase = 2 * pi * index / FRAME_COUNT
    breath = sin(phase)
    width = round(CANVAS_WIDTH * (1 + 0.0025 * breath))
    height = round(base_height * (1 + 0.006 * breath))
    character = source.resize((width, height), Image.Resampling.LANCZOS)
    frame = Image.new("RGBA", (CANVAS_WIDTH + 12, canvas_height), (0, 0, 0, 0))
    offset_x = (frame.width - width) // 2
    offset_y = canvas_height - height - 8
    frame.alpha_composite(character, (offset_x, offset_y))

    blink = blink_amount(index)
    if blink > 0:
        overlay = Image.new("RGBA", frame.size, (0, 0, 0, 0))
        draw = ImageDraw.Draw(overlay)
        for left_n, top_n, right_n, bottom_n in eye_boxes:
            left = offset_x + round(left_n * width)
            top = offset_y + round(top_n * height)
            right = offset_x + round(right_n * width)
            bottom = offset_y + round(bottom_n * height)
            middle = (top + bottom) // 2
            cover = round((bottom - top) * 0.54 * blink)
            eyelid = (126, 224, 36, 255)
            draw.rounded_rectangle((left, top, right, min(bottom, top + cover)), radius=18, fill=eyelid)
            draw.rounded_rectangle((left, max(top, bottom - cover), right, bottom), radius=18, fill=eyelid)
            if blink > 0.82:
                draw.arc((left + 5, middle - 8, right - 5, middle + 10), 5, 175, fill=(35, 92, 18, 225), width=3)
        overlay = overlay.filter(ImageFilter.GaussianBlur(0.55))
        frame.alpha_composite(overlay)

    # Mantém os 150 quadros distintos para o codificador, com marcadores
    # praticamente transparentes fora da área visual do personagem.
    frame.putpixel((0, 0), (0, 0, 0, index % 16))
    frame.putpixel((1, 0), (0, 0, 0, index // 16))
    frames.append(frame)

frame_durations = [33 + (1 if index % 3 == 0 else 0) for index in range(FRAME_COUNT)]
frames[0].save(
    OUTPUT,
    save_all=True,
    append_images=frames[1:],
    duration=frame_durations,
    loop=1,
    lossless=False,
    quality=96,
    method=4,
)

print(f"Gerado: {OUTPUT} ({FRAME_COUNT} quadros, fundo transparente)")
