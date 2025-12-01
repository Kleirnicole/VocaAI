import os
import pandas as pd
import joblib
from sentence_transformers import SentenceTransformer, util

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

COURSE_DESC_FILE = os.path.join(BASE_DIR, "course_descriptions.xlsx")
MODEL_FILE = os.path.join(BASE_DIR, "best_model.pkl")
FEATURE_ENCODERS_FILE = os.path.join(BASE_DIR, "feature_encoders.pkl")
TARGET_ENCODER_FILE = os.path.join(BASE_DIR, "target_encoder.pkl")
RIASEC_ENCODER_FILE = os.path.join(BASE_DIR, "riasec_encoder.pkl")

# --------- LOAD MODEL ONCE (NOT EVERY REQUEST) ----------
model = joblib.load(MODEL_FILE)
feature_encoders = joblib.load(FEATURE_ENCODERS_FILE)
target_encoder = joblib.load(TARGET_ENCODER_FILE)
riasec_encoder = joblib.load(RIASEC_ENCODER_FILE)
course_df = pd.read_excel(COURSE_DESC_FILE)
course_df.columns = course_df.columns.str.strip()
course_df["Course Name"] = course_df["Course Name"].str.strip()
course_df["Course Name Clean"] = course_df["Course Name"].str.lower()

# Load semantic model ONCE
embedder = SentenceTransformer("all-MiniLM-L6-v2")
course_embeddings = embedder.encode(
    course_df["Description"].tolist(), convert_to_tensor=True
)

# --------------------------------------------------------
def prepare_input(data):
    df = pd.DataFrame([{f"q{i}": data.get(f"q{i}", "") for i in range(1, 43)}])

    for col in df.columns:
        if col in feature_encoders:
            val = str(df[col].iloc[0]).lower()
            cleaned = "Yes" if val in ["1", "yes", "y", "true"] else "No"
            if cleaned not in feature_encoders[col].classes_:
                cleaned = feature_encoders[col].classes_[0]
            df[col] = feature_encoders[col].transform([cleaned])

    codes = data.get("code") or data.get("top_3_types") or ""
    codes = codes.split(",") if codes else []

    if codes:
        riasec_features = pd.DataFrame(
            riasec_encoder.transform([codes]),
            columns=riasec_encoder.classes_
        )
        df = pd.concat([df, riasec_features], axis=1)

    return df


def survey_to_text(data):
    return " ".join([f"{k}:{data[k]}" for k in data if k.startswith("q")])


def get_course_info(course_name_clean):
    match = course_df[course_df["Course Name Clean"] == course_name_clean.lower()]
    return match["Description"].values[0] if not match.empty else "No description available"


def predict(data):
    # ML PREDICTION
    X = prepare_input(data)
    probs = model.predict_proba(X)[0]
    top_idx = probs.argsort()[::-1][:2]
    top_courses = target_encoder.inverse_transform(top_idx)

    recommended = top_courses[0]
    suggested = top_courses[1]

    rec_score = round(probs[top_idx[0]] * 100, 2)
    sug_score = round(probs[top_idx[1]] * 100, 2)

    recommended_desc = get_course_info(recommended)

    # SEMANTIC AI
    student_text = survey_to_text(data)
    student_embed = embedder.encode(student_text, convert_to_tensor=True)

    scores = util.cos_sim(student_embed, course_embeddings)[0]
    sem_idx = int(scores.argmax())
    sem_course = course_df.iloc[sem_idx]["Course Name"]
    sem_score = round(scores[sem_idx].item() * 100, 2)

    return {
        "recommended_course": recommended,
        "recommended_score": rec_score,
        "recommended_description": recommended_desc,

        "suggested_course": suggested,
        "suggested_score": sug_score,

        "semantic_course": sem_course,
        "semantic_score": sem_score,

        "ml_top_courses": list(top_courses),
        "ml_top_scores": [round(probs[i] * 100, 2) for i in top_idx]
    }
