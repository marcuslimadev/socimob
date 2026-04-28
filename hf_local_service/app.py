import os
from typing import List, Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from dotenv import load_dotenv
import logging

load_dotenv()

os.environ.setdefault("HF_HUB_DISABLE_XET", "1")
os.environ.setdefault("HF_HUB_DISABLE_PROGRESS_BARS", "1")
os.environ.setdefault("TOKENIZERS_PARALLELISM", "false")
os.environ.setdefault("OMP_NUM_THREADS", "1")
os.environ.setdefault("MKL_NUM_THREADS", "1")
os.environ.setdefault("NUMEXPR_NUM_THREADS", "1")

MODEL_NAME = os.getenv("HF_LOCAL_MODEL", "nomic-ai/nomic-embed-text-v1.5")
CACHE_DIR = os.getenv("HF_LOCAL_CACHE_DIR", os.path.expanduser("~/.cache/hf_local_model"))
DEVICE = os.getenv("HF_LOCAL_DEVICE") or None
MAX_TEXTS = int(os.getenv("HF_LOCAL_MAX_TEXTS", "64"))
VALID_TASK_TYPES = {"search_document", "search_query", "clustering", "classification"}

os.makedirs(CACHE_DIR, exist_ok=True)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("hf_local_service")

logger.info(f"Loading local embedding model: {MODEL_NAME}")
model = SentenceTransformer(
    MODEL_NAME,
    cache_folder=CACHE_DIR,
    device=DEVICE,
    trust_remote_code=os.getenv("HF_LOCAL_TRUST_REMOTE_CODE", "true").lower() == "true",
)
logger.info("Model loaded successfully")

app = FastAPI(title="Local Embeddings Service")

class EmbedRequest(BaseModel):
    texts: List[str]
    task_type: Optional[str] = None

class EmbedResponse(BaseModel):
    success: bool
    embeddings: List[List[float]]
    model: str
    dimensions: int

def apply_task_prefix(text: str, task_type: Optional[str]) -> str:
    text = text.strip()
    if not task_type:
        return text

    if task_type not in VALID_TASK_TYPES:
        raise HTTPException(status_code=400, detail=f"invalid task_type: {task_type}")

    for prefix in VALID_TASK_TYPES:
        if text.startswith(f"{prefix}:"):
            return text

    return f"{task_type}: {text}"

@app.get("/health")
def health():
    return {
        "success": True,
        "model": MODEL_NAME,
        "max_texts": MAX_TEXTS,
    }

@app.post("/embed", response_model=EmbedResponse)
def embed(request: EmbedRequest):
    if not request.texts:
        raise HTTPException(status_code=400, detail="texts cannot be empty")
    if len(request.texts) > MAX_TEXTS:
        raise HTTPException(status_code=400, detail=f"texts cannot exceed {MAX_TEXTS} items")

    texts = [apply_task_prefix(text, request.task_type) for text in request.texts if text and text.strip()]
    if not texts:
        raise HTTPException(status_code=400, detail="texts cannot contain only blank values")

    embeddings = model.encode(
        texts,
        show_progress_bar=False,
        convert_to_numpy=True,
        normalize_embeddings=True,
    )
    return {
        "success": True,
        "embeddings": embeddings.tolist(),
        "model": MODEL_NAME,
        "dimensions": len(embeddings[0]) if len(embeddings) else 0,
    }
