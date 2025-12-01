import joblib
import pandas as pd
import os

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

COURSE_DESC_FILE = os.path.join(BASE_DIR, "course_descriptions.xlsx")
MODEL_FILE = os.path.join(BASE_DIR, "best_model.pkl")
FEATURE_ENCODERS_FILE = os.path.join(BASE_DIR, "feature_encoders.pkl")
TARGET_ENCODER_FILE = os.path.join(BASE_DIR, "target_encoder.pkl")
RIASEC_ENCODER_FILE = os.path.join(BASE_DIR, "riasec_encoder.pkl")

# ==== LAZY LOAD (important!) ====
model = None
feature_encoders = None
target_encoder = None
riasec_encoder = None
embedder = None
course_df = None

def load_all():
    global model, feature_encoders, target_encoder, riasec_encoder, course_df, embedder

    if model is None:
        model = joblib.load(MODEL_FILE)
        feature_encoders = joblib.load(FEATURE_ENCODERS_FILE)
        target_encoder = joblib.load(TARGET_ENCODER_FILE)
        riasec_encoder = joblib.load(RIASEC_ENCODER_FILE)
        course_df = pd.read_excel(COURSE_DESC_FILE)

        # Only load when needed (prevent Render crash)
        from sentence_transformers import SentenceTransformer
        from sentence_transformers import util
        embedder = SentenceTransformer("all-MiniLM-L6-v2")
        print("Models Loaded Successfully!")

    return model, feature_encoders, target_encoder, riasec_encoder, course_df, embedder


def predict(data):
    model, feature_encoders, target_encoder, riasec_encoder, course_df, embedder = load_all()

    # your ML logic ...
    # (no change required)
