import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    headers: { 'Accept': 'application/json' },
})

export const useApi = () => ({
    // Cases
    createCase: (data) => api.post('/cases', data),
    getCase: (id) => api.get(`/cases/${id}`),
    generateCharacters: (id) => api.post(`/cases/${id}/generate-characters`),
    approveCharacter: (id, optionId) => api.post(`/cases/${id}/approve-character`, { character_option_id: optionId }),
    generateScenes: (id) => api.post(`/cases/${id}/generate-scenes`),
    approveImages: (id) => api.post(`/cases/${id}/approve-images`),
    generateVoiceover: (id, voiceName) => api.post(`/cases/${id}/generate-voiceover`, { voice_name: voiceName }),
    renderVideo: (id) => api.post(`/cases/${id}/render-video`),
    regenerateScene: (caseId, shotId) => api.post(`/cases/${caseId}/regenerate-scene/${shotId}`),
    regenerateVideo: (caseId, shotId) => api.post(`/cases/${caseId}/regenerate-video/${shotId}`),
})
