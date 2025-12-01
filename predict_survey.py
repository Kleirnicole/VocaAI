import joblib
import pandas as pd
import os
import numpy as np

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
        embedder = SentenceTransformer("all-MiniLM-L6-v2")
        print("Models Loaded Successfully!")

    return model, feature_encoders, target_encoder, riasec_encoder, course_df, embedder


def predict(data):
    model, feature_encoders, target_encoder, riasec_encoder, course_df, embedder = load_all()

    # === Example ML pipeline ===
    # 1. Extract features from incoming JSON
    features = []
    for col in feature_encoders.keys():
        val = data.get(col, "")
        encoder = feature_encoders[col]
        encoded_val = encoder.transform([val])[0]
        features.append(encoded_val)

    X = np.array(features).reshape(1, -1)

    # 2. Run prediction
    y_pred = model.predict(X)[0]
    y_score = max(model.predict_proba(X)[0])  # highest probability

    # 3. Decode target
    recommended_course = target_encoder.inverse_transform([y_pred])[0]

    # 4. Find description from course_df
    desc_row = course_df.loc[course_df['course'] == recommended_course]
    recommended_description = (
        desc_row['description'].values[0] if not desc_row.empty else "No description available."
    )

    # 5. Suggested alternative (optional: top 2nd course)
    proba = model.predict_proba(X)[0]
    top2_idx = np.argsort(proba)[::-1][1]  # second highest
    suggested_course = target_encoder.inverse_transform([top2_idx])[0]
    suggested_score = float(proba[top2_idx])

    # === Return JSON-friendly dict ===
    return {
        "recommended_course": recommended_course,
        "recommended_score": float(y_score),
        "suggested_course": suggested_course,
        "suggested_score": suggested_score,
        "recommended_description": recommended_description,
    }
