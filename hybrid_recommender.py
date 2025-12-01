import pandas as pd
import numpy as np
import joblib
from sentence_transformers import SentenceTransformer, util

# Load trained model and encoders
model = joblib.load("best_model.pkl")
feature_encoders = joblib.load("feature_encoders.pkl")
target_encoder = joblib.load("target_encoder.pkl")

# Load course descriptions
course_df = pd.read_excel("course_descriptions.xlsx")
course_df.columns = course_df.columns.str.strip()
course_df["Course Name"] = course_df["Course Name"].str.strip()
course_df["Course Name Clean"] = course_df["Course Name"].str.lower()
course_texts = course_df['Description'].tolist()

# Load student survey answers
student_df = pd.read_excel("training_dataset.xlsx")
student_df.columns = student_df.columns.str.strip()
student_row = student_df.iloc[0]  # You can loop through all students later

# Convert survey answers to text for semantic embedding
def survey_to_text(row):
    return " ".join([f"{col}: {row[col]}" for col in row.index if col.startswith("q")])

student_text = survey_to_text(student_row)

# Encode survey answers for ML
survey_columns = [col for col in student_row.index if col.startswith("q")]
print("✅ Survey columns used:", survey_columns)
print("✅ Feature count:", len(survey_columns))

encoded = []
for col in survey_columns:
    val = student_row[col]
    if col in feature_encoders:
        try:
            val = feature_encoders[col].transform([str(val)])[0]
        except ValueError:
            print(f"⚠️ Unseen label for {col}: {val}")
            val = 0
    else:
        val = 0
    encoded.append(val)

encoded_array = np.array(encoded, dtype=float).reshape(1, -1)

# ML prediction
probs = model.predict_proba(encoded_array)[0]
ml_top_idx = np.argmax(probs)
ml_confidence = probs[ml_top_idx]
ml_course = target_encoder.inverse_transform([ml_top_idx])[0]

# Semantic matching
embedder = SentenceTransformer('all-MiniLM-L6-v2')
course_embeddings = embedder.encode(course_texts, convert_to_tensor=True)
student_embedding = embedder.encode(student_text, convert_to_tensor=True)
scores = util.cos_sim(student_embedding, course_embeddings)[0]

sem_top_idx = int(scores.argmax())
sem_course_clean = course_df.iloc[sem_top_idx]['Course Name Clean']
sem_course_display = course_df.iloc[sem_top_idx]['Course Name']
sem_score = scores[sem_top_idx].item()

# Helper: get course description
def get_description(course_name):
    match = course_df[course_df["Course Name Clean"] == course_name.strip().lower()]
    return match["Description"].values[0] if not match.empty else "No description available"

# Final decision logic
ML_CONFIDENCE_THRESHOLD = 0.7

if ml_confidence >= ML_CONFIDENCE_THRESHOLD:
    recommended_course = {
        "Course": ml_course,
        "Confidence": round(ml_confidence * 100, 2),
        "Description": get_description(ml_course.strip().lower())
    }
    suggested_course = {
        "Course": sem_course_display,
        "Confidence": round(sem_score * 100, 2)
    }
else:
    recommended_course = {
        "Course": sem_course_display,
        "Confidence": round(sem_score * 100, 2),
        "Description": get_description(sem_course_clean)
    }
    suggested_course = {
        "Course": ml_course,
        "Confidence": round(ml_confidence * 100, 2)
    }

# Output
print("\n🎓 FINAL RECOMMENDATION")
print(f"🎯 Recommended Course: {recommended_course['Course']} ({recommended_course['Confidence']}%)")
print(f"   📘 Description: {recommended_course['Description']}")
print(f"💡 Suggested Course: {suggested_course['Course']} ({suggested_course['Confidence']}%)")