from pathlib import Path

import numpy as np
from PIL import Image
from scipy import ndimage


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "client/public/assets/turtle-walk-sheet.png"
OUTPUT = ROOT / "client/public/assets/turtle-walk-smooth.webp"

COLS = 4
ROWS = 2
FRAME_COUNT = COLS * ROWS


def isolate_character(frame: Image.Image) -> Image.Image:
    rgba = np.array(frame.convert("RGBA"))
    mask = rgba[:, :, 3] > 24
    labels, count = ndimage.label(mask)
    if count:
        sizes = ndimage.sum(mask, labels, range(1, count + 1))
        main_label = int(np.argmax(sizes)) + 1
        keep = labels == main_label
        rgba[:, :, 3] = np.where(keep, rgba[:, :, 3], 0)

    clean = Image.fromarray(rgba, "RGBA")
    bbox = clean.getbbox()
    if not bbox:
        return clean

    subject = clean.crop(bbox)
    canvas_w, canvas_h = frame.size
    max_w = int(canvas_w * 0.88)
    max_h = int(canvas_h * 0.94)
    scale = min(max_w / subject.width, max_h / subject.height)
    resized = subject.resize(
        (max(1, round(subject.width * scale)), max(1, round(subject.height * scale))),
        Image.Resampling.LANCZOS,
    )

    canvas = Image.new("RGBA", frame.size, (0, 0, 0, 0))
    x = (canvas_w - resized.width) // 2
    y = canvas_h - resized.height
    canvas.alpha_composite(resized, (x, y))
    return canvas


def main() -> None:
    sheet = Image.open(SOURCE).convert("RGBA")
    frame_w = sheet.width // COLS
    frame_h = sheet.height // ROWS
    keyframes = []

    for index in range(FRAME_COUNT):
        col = index % COLS
        row = index // COLS
        crop = sheet.crop((col * frame_w, row * frame_h, (col + 1) * frame_w, (row + 1) * frame_h))
        keyframes.append(isolate_character(crop))

    # Use somente poses próximas e inteiras. Misturar imagens por opacidade cria
    # rostos, chaves e membros duplicados; a sequência espelhada mantém o passo
    # contínuo sem qualquer quadro fantasma.
    frames = [keyframes[index] for index in (0, 1, 2, 3, 2, 1, 0, 1)]

    frames[0].save(
        OUTPUT,
        format="WEBP",
        save_all=True,
        append_images=frames[1:],
        duration=230,
        loop=0,
        lossless=False,
        quality=88,
        method=3,
    )
    print(f"Generated {OUTPUT} with {len(frames)} frames")


if __name__ == "__main__":
    main()
