import sys
import json
import joblib
import pandas as pd
import os
import io
from sentence_transformers import SentenceTransformer, util
import numpy as np

# Fix UTF-8 print
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

# File paths
COURSE_DESC_FILE = "C:/wamp64/www/VocAItion/Student/course_descriptions.xlsx"
MODEL_FILE = "C:/wamp64/www/VocAItion/Student/best_model.pkl"  # Calibrated Random Forest
FEATURE_ENCODERS_FILE = "C:/wamp64/www/VocAItion/Student/feature_encoders.pkl"
TARGET_ENCODER_FILE = "C:/wamp64/www/VocAItion/Student/target_encoder.pkl"
RIASEC_ENCODER_FILE = "C:/wamp64/www/VocAItion/Student/riasec_encoder.pkl"

# Load model + encoders
def load_model_and_encoders():
    # NOTE: best_model.pkl should be a calibrated Random Forest
    model = joblib.load(MODEL_FILE)
    feature_encoders = joblib.load(FEATURE_ENCODERS_FILE)
    target_encoder = joblib.load(TARGET_ENCODER_FILE)
    riasec_encoder = joblib.load(RIASEC_ENCODER_FILE)
    return model, feature_encoders, target_encoder, riasec_encoder

# Prepare ML input
def prepare_input(data, feature_encoders, riasec_encoder):
    df = pd.DataFrame([{f"q{i}": data.get(f"q{i}", "") for i in range(1, 43)}])

    for col in df.columns:
        if col in feature_encoders:
            # Normalize answers
            df[col] = df[col].apply(
                lambda x: "Yes" if str(x).lower() in ["1", "yes", "y", "true"] else
                          "No" if str(x).lower() in ["0", "no", "n", "false"] else
                          str(x)
            )
            if df[col].iloc[0] not in feature_encoders[col].classes_:
                df[col] = feature_encoders[col].classes_[0]

            df[col] = feature_encoders[col].transform(df[col])

    # RIASEC codes
    if "code" in data:
        codes = data["code"].split(",")
    elif "top_3_types" in data:
        codes = data["top_3_types"].split(",")
    else:
        codes = []

    riasec_features = pd.DataFrame(
        riasec_encoder.transform([codes]),
        columns=riasec_encoder.classes_
    )

    df = pd.concat([df, riasec_features], axis=1)
    return df

# Convert survey to text for semantic AI
def survey_to_text(data):
    return " ".join([f"{key}: {data[key]}" for key in data if key.startswith("q")])

# Fetch course description
def get_course_info(course_df, course_name_clean):
    match = course_df[course_df["Course Name Clean"] == course_name_clean]
    if not match.empty:
        return match["Description"].values[0]
    return "No description available"

# ------------------------- MAIN PROCESS -------------------------
def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No input JSON provided"}))
        sys.exit(1)

    file_path = sys.argv[1]

    if not os.path.exists(file_path):
        print(json.dumps({"error": "JSON file not found"}))
        sys.exit(1)

    try:
        with open(file_path, "r", encoding="utf-8") as f:
            data = json.load(f)
    except Exception as e:
        print(json.dumps({"error": f"JSON read error: {e}"}))
        sys.exit(1)

    try:
        model, feature_encoders, target_encoder, riasec_encoder = load_model_and_encoders()

        course_df = pd.read_excel(COURSE_DESC_FILE)
        course_df.columns = course_df.columns.str.strip()
        course_df["Course Name"] = course_df["Course Name"].str.strip()
        course_df["Course Name Clean"] = course_df["Course Name"].str.lower()

    except Exception as e:
        print(json.dumps({"error": f"Failed loading model/data: {e}"}))
        sys.exit(1)

    try:
        # ==== ML PREDICTION ====
        X = prepare_input(data, feature_encoders, riasec_encoder)
        probs = model.predict_proba(X)[0]

        top_indices = probs.argsort()[::-1][:3]
        top_courses = target_encoder.inverse_transform(top_indices)

        ml_course = top_courses[0]
        ml_course_clean = ml_course.lower().strip()
        ml_score = round(probs[top_indices[0]] * 100, 2)

        # ==== SEMANTIC AI ====
        embedder = SentenceTransformer('all-MiniLM-L6-v2')

        student_text = survey_to_text(data)
        course_texts = course_df["Description"].tolist()

        course_embeddings = embedder.encode(course_texts, convert_to_tensor=True)
        student_embedding = embedder.encode(student_text, convert_to_tensor=True)

        scores = util.cos_sim(student_embedding, course_embeddings)[0]

        sem_idx = int(scores.argmax())
        sem_course = course_df.iloc[sem_idx]["Course Name"]
        sem_course_clean = course_df.iloc[sem_idx]["Course Name Clean"]
        sem_score = round(scores[sem_idx].item() * 100, 2)

        # ==== ML PREDICTION ====
        X = prepare_input(data, feature_encoders, riasec_encoder)
        probs = model.predict_proba(X)[0]

        # Get top 2 courses from ML
        top_indices = probs.argsort()[::-1][:2]
        top_courses = target_encoder.inverse_transform(top_indices)

        ml_course = top_courses[0]
        ml_course_clean = ml_course.lower().strip()
        ml_score = round(probs[top_indices[0]] * 100, 2)

        suggested_course = top_courses[1]
        suggested_score = round(probs[top_indices[1]] * 100, 2)

        final_course = ml_course
        final_score = ml_score
        final_desc = get_course_info(course_df, ml_course_clean)

        # ==== JSON OUTPUT ====
        result = {
            "recommended_course": final_course,
            "recommended_score": final_score,
            "recommended_description": final_desc,
            "suggested_course": suggested_course,
            "suggested_score": suggested_score,
            "ml_top_courses": list(top_courses),
            "ml_top_scores": [round(probs[i] * 100, 2) for i in top_indices]
        }

        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({"error": f"Prediction error: {e}"}))
        sys.exit(1)

# Run program
if __name__ == "__main__":
    main()