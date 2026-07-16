from pathlib import Path

import cv2
import numpy as np
from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
SOURCE = Path.home() / "Downloads" / "pika-be522af8-f455-429c-ae54-e3e24cacca04.mp4"
OUTPUT = ROOT / "client" / "public" / "assets" / "exclusiva-mascot-once.webp"


capture = cv2.VideoCapture(str(SOURCE))
fps = capture.get(cv2.CAP_PROP_FPS) or 30
frame_step = max(1, round(fps / 15))
rgba_frames: list[np.ndarray] = []
frame_index = 0

while True:
    ok, bgr = capture.read()
    if not ok:
        break
    if frame_index % frame_step:
        frame_index += 1
        continue
    frame_index += 1

    rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)
    distance_from_white = 255 - rgb.min(axis=2)
    foreground_seed = (distance_from_white > 18).astype(np.uint8)
    count, labels, stats, centroids = cv2.connectedComponentsWithStats(foreground_seed, 8)
    selected = np.zeros(foreground_seed.shape, dtype=np.uint8)

    for label in range(1, count):
        x, y, width, height, area = stats[label]
        center_x, _ = centroids[label]
        if area >= 80 and center_x >= rgb.shape[1] * 0.24:
            selected[labels == label] = 255

    selected = cv2.morphologyEx(selected, cv2.MORPH_CLOSE, np.ones((5, 5), np.uint8))
    contours, _ = cv2.findContours(selected, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    silhouette = np.zeros_like(selected)
    cv2.drawContours(silhouette, contours, -1, 255, thickness=cv2.FILLED)
    alpha = cv2.GaussianBlur(silhouette, (5, 5), 0.9)
    rgba_frames.append(np.dstack((rgb, alpha)))

capture.release()

if not rgba_frames:
    raise RuntimeError(f"Nenhum quadro encontrado em {SOURCE}")

union = np.maximum.reduce([frame[:, :, 3] for frame in rgba_frames])
ys, xs = np.where(union > 8)
padding = 8
left = max(0, int(xs.min()) - padding)
top = max(0, int(ys.min()) - padding)
right = min(union.shape[1], int(xs.max()) + padding + 1)
bottom = min(union.shape[0], int(ys.max()) + padding + 1)

frames = [Image.fromarray(frame[top:bottom, left:right], "RGBA") for frame in rgba_frames]
frame_duration = max(1, round(1000 * frame_step / fps))
frames[0].save(
    OUTPUT,
    save_all=True,
    append_images=frames[1:],
    duration=frame_duration,
    loop=1,
    lossless=False,
    quality=88,
    method=0,
)

print(f"Gerado: {OUTPUT} ({len(frames)} quadros, {right-left}x{bottom-top}, {frame_duration} ms/quadro)")
