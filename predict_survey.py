from flask import Flask, request, jsonify
import json
import os
import pandas as pd
import joblib
from sentence_transformers import SentenceTransformer, util

# ---------------- CONFIG ----------------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

COURSE_DESC_FILE = os.path.join(BASE_DIR, "course_descriptions.xlsx")
MODEL_FILE = os.path.join(BASE_DIR, "best_model.pkl")
FEATURE_ENCODERS_FILE = os.path.join(BASE_DIR, "feature_encoders.pkl")
TARGET_ENCODER_FILE = os.path.join(BASE_DIR, "target_encoder.pkl")
RIASEC_ENCODER_FILE = os.path.join(BASE_DIR, "riasec_encoder.pkl")

app = Flask(__name__)

# ---------------- HELPER FUNCTIONS ----------------
def load_model_and_encoders():
    model = joblib.load(MODEL_FILE)
    feature_encoders = joblib.load(FEATURE_ENCODERS_FILE)
    target_encoder = joblib.load(TARGET_ENCODER_FILE)
    riasec_encoder = joblib.load(RIASEC_ENCODER_FILE)
    return model, feature_encoders, target_encoder, riasec_encoder

def prepare_input(data, feature_encoders, riasec_encoder):
    import pandas as pd
    df = pd.DataFrame([{f"q{i}": data.get(f"q{i}", "") for i in range(1, 43)}])
    for col in df.columns:
        if col in feature_encoders:
            df[col] = df[col].apply(lambda x: "Yes" if str(x).lower() in ["1","yes","y","true"] else "No")
            if df[col].iloc[0] not in feature_encoders[col].classes_:
                df[col] = feature_encoders[col].classes_[0]
            df[col] = feature_encoders[col].transform(df[col])

    codes = data.get("code") or data.get("top_3_types") or ""
    codes = codes.split(",") if codes else []
    if codes:
        riasec_features = pd.DataFrame(riasec_encoder.transform([codes]), columns=riasec_encoder.classes_)
        df = pd.concat([df, riasec_features], axis=1)
    return df

def survey_to_text(data):
    return " ".join([f"{key}: {data[key]}" for key in data if key.startswith("q")])

def get_course_info(course_df, course_name_clean):
    match = course_df[course_df["Course Name Clean"] == course_name_clean.lower().strip()]
    return match["Description"].values[0] if not match.empty else "No description available"

def predict(data):
    model, feature_encoders, target_encoder, riasec_encoder = load_model_and_encoders()
    course_df = pd.read_excel(COURSE_DESC_FILE)
    course_df.columns = course_df.columns.str.strip()
    course_df["Course Name"] = course_df["Course Name"].str.strip()
    course_df["Course Name Clean"] = course_df["Course Name"].str.lower()

    # ----- ML Prediction -----
    X = prepare_input(data, feature_encoders, riasec_encoder)
    probs = model.predict_proba(X)[0]
    top_indices = probs.argsort()[::-1][:2]
    top_courses = target_encoder.inverse_transform(top_indices)

    recommended_course = top_courses[0]
    recommended_score = round(probs[top_indices[0]] * 100, 2)
    suggested_course = top_courses[1]
    suggested_score = round(probs[top_indices[1]] * 100, 2)
    recommended_description = get_course_info(course_df, recommended_course)

    # ----- Semantic AI -----
    embedder = SentenceTransformer('all-MiniLM-L6-v2')
    student_text = survey_to_text(data)
    course_texts = course_df["Description"].tolist()
    course_embeddings = embedder.encode(course_texts, convert_to_tensor=True)
    student_embedding = embedder.encode(student_text, convert_to_tensor=True)
    scores = util.cos_sim(student_embedding, course_embeddings)[0]
    sem_idx = int(scores.argmax())
    sem_course = course_df.iloc[sem_idx]["Course Name"]
    sem_score = round(scores[sem_idx].item() * 100, 2)

    return {
        "recommended_course": recommended_course,
        "recommended_score": recommended_score,
        "recommended_description": recommended_description,
        "suggested_course": suggested_course,
        "suggested_score": suggested_score,
        "semantic_course": sem_course,
        "semantic_score": sem_score,
        "ml_top_courses": list(top_courses),
        "ml_top_scores": [round(probs[i]*100,2) for i in top_indices]
    }

# ---------------- API ROUTE ----------------
@app.route("/predict", methods=["POST"])
def predict_api():
    data = request.get_json()
    if not data:
        return jsonify({"error": "No JSON provided"}), 400
    try:
        result = predict(data)
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# ---------------- RUN ----------------
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port)
