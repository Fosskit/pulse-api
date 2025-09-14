-- PostgreSQL Database Setup for EMR FHIR System
-- This file contains PostgreSQL-specific configurations and optimizations

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "btree_gin";

-- Create custom types for better data integrity
CREATE TYPE gender_type AS ENUM ('male', 'female', 'other', 'unknown');
CREATE TYPE encounter_status AS ENUM ('planned', 'arrived', 'triaged', 'in-progress', 'onleave', 'finished', 'cancelled', 'entered-in-error');
CREATE TYPE observation_status AS ENUM ('registered', 'preliminary', 'final', 'amended', 'corrected', 'cancelled', 'entered-in-error', 'unknown');
CREATE TYPE medication_status AS ENUM ('active', 'on-hold', 'cancelled', 'completed', 'entered-in-error', 'stopped', 'draft', 'unknown');
CREATE TYPE service_request_status AS ENUM ('draft', 'active', 'on-hold', 'revoked', 'completed', 'entered-in-error', 'unknown');
CREATE TYPE invoice_status AS ENUM ('draft', 'issued', 'balanced', 'cancelled', 'entered-in-error');

-- Create indexes for better performance
-- Patient search indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patients_code ON patients USING btree (code);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patients_facility ON patients USING btree (facility_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patients_created_at ON patients USING btree (created_at);

-- Patient demographics search indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_demographics_names ON patient_demographics USING gin (
    to_tsvector('english', coalesce(given_name, '') || ' ' || coalesce(family_name, ''))
);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_demographics_phone ON patient_demographics USING btree (phone_number);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_demographics_email ON patient_demographics USING btree (email);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_demographics_dob ON patient_demographics USING btree (date_of_birth);

-- Patient address indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_addresses_patient ON patient_addresses USING btree (patient_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_addresses_gazetteer ON patient_addresses USING btree (province_id, district_id, commune_id, village_id);

-- Patient identity indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_identities_patient ON patient_identities USING btree (patient_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_identities_card ON patient_identities USING btree (card_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_identities_code ON patient_identities USING btree (identity_code);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_patient_identities_dates ON patient_identities USING btree (start_date, end_date);

-- Visit indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visits_patient ON visits USING btree (patient_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visits_facility ON visits USING btree (facility_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visits_dates ON visits USING btree (admitted_at, discharged_at);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visits_status ON visits USING btree (visit_type_id, admission_type_id);

-- Encounter indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_encounters_visit ON encounters USING btree (visit_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_encounters_type ON encounters USING btree (encounter_type_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_encounters_location ON encounters USING btree (department_id, room_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_encounters_dates ON encounters USING btree (started_at, ended_at);

-- Observation indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_observations_encounter ON observations USING btree (encounter_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_observations_patient ON observations USING btree (patient_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_observations_concept ON observations USING btree (observation_concept_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_observations_recorded_at ON observations USING btree (recorded_at);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_observations_service_request ON observations USING btree (service_request_id);

-- Medication indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_requests_visit ON medication_requests USING btree (visit_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_requests_concept ON medication_requests USING btree (medication_concept_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_requests_status ON medication_requests USING btree (status_id);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_dispenses_request ON medication_dispenses USING btree (medication_request_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_dispenses_dispensed_at ON medication_dispenses USING btree (dispensed_at);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_administrations_request ON medication_administrations USING btree (medication_request_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_medication_administrations_administered_at ON medication_administrations USING btree (administered_at);

-- Service request indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_service_requests_visit ON service_requests USING btree (visit_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_service_requests_type ON service_requests USING btree (request_type);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_service_requests_status ON service_requests USING btree (status_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_service_requests_concept ON service_requests USING btree (service_concept_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_service_requests_dates ON service_requests USING btree (requested_at, completed_at);

-- Invoice indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_visit ON invoices USING btree (visit_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_patient ON invoices USING btree (patient_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_status ON invoices USING btree (status_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_payment_type ON invoices USING btree (payment_type_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_dates ON invoices USING btree (invoice_date, due_date);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoice_items_invoice ON invoice_items USING btree (invoice_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoice_items_service ON invoice_items USING btree (service_id);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoice_payments_invoice ON invoice_payments USING btree (invoice_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoice_payments_date ON invoice_payments USING btree (payment_date);

-- Gazetteer indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_gazetteers_parent ON gazetteers USING btree (parent_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_gazetteers_type ON gazetteers USING btree (type);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_gazetteers_name ON gazetteers USING gin (name gin_trgm_ops);

-- Facility indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_facilities_name ON facilities USING gin (name gin_trgm_ops);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_departments_facility ON departments USING btree (facility_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_rooms_department ON rooms USING btree (department_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_services_facility ON services USING btree (facility_id);

-- Clinical form template indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clinical_form_templates_category ON clinical_form_templates USING btree (category);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_clinical_form_templates_active ON clinical_form_templates USING btree (active);

-- Concept and terminology indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_concepts_category ON concepts USING btree (concept_category_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_concepts_terminology ON concepts USING btree (terminology_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_terms_concept ON terms USING btree (concept_id);

-- Activity log indexes for audit trail
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_subject ON activity_log USING btree (subject_type, subject_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_causer ON activity_log USING btree (causer_type, causer_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_created_at ON activity_log USING btree (created_at);

-- Permission indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_model_has_permissions_model ON model_has_permissions USING btree (model_type, model_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_model_has_roles_model ON model_has_roles USING btree (model_type, model_id);

-- OAuth indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_oauth_access_tokens_user ON oauth_access_tokens USING btree (user_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_oauth_access_tokens_revoked ON oauth_access_tokens USING btree (revoked);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_personal_access_tokens_tokenable ON personal_access_tokens USING btree (tokenable_type, tokenable_id);

-- Create views for common queries
CREATE OR REPLACE VIEW patient_summary AS
SELECT 
    p.id,
    p.code,
    p.facility_id,
    pd.given_name,
    pd.family_name,
    pd.date_of_birth,
    pd.gender,
    pd.phone_number,
    pd.email,
    pa.address_line_1,
    pa.postal_code,
    g_province.name as province_name,
    g_district.name as district_name,
    g_commune.name as commune_name,
    g_village.name as village_name,
    pi.identity_code as insurance_code,
    c.card_type,
    pi.is_beneficiary,
    p.created_at,
    p.updated_at
FROM patients p
LEFT JOIN patient_demographics pd ON p.id = pd.patient_id
LEFT JOIN patient_addresses pa ON p.id = pa.patient_id AND pa.is_primary = true
LEFT JOIN gazetteers g_province ON pa.province_id = g_province.id
LEFT JOIN gazetteers g_district ON pa.district_id = g_district.id
LEFT JOIN gazetteers g_commune ON pa.commune_id = g_commune.id
LEFT JOIN gazetteers g_village ON pa.village_id = g_village.id
LEFT JOIN patient_identities pi ON p.id = pi.patient_id AND pi.start_date <= CURRENT_DATE AND (pi.end_date IS NULL OR pi.end_date >= CURRENT_DATE)
LEFT JOIN cards c ON pi.card_id = c.id;

CREATE OR REPLACE VIEW visit_summary AS
SELECT 
    v.id,
    v.patient_id,
    p.code as patient_code,
    pd.given_name || ' ' || pd.family_name as patient_name,
    v.facility_id,
    f.name as facility_name,
    v.visit_type_id,
    v.admission_type_id,
    v.admitted_at,
    v.discharged_at,
    v.discharge_type_id,
    v.visit_outcome_id,
    COUNT(e.id) as encounter_count,
    COUNT(mr.id) as medication_count,
    COUNT(sr.id) as service_request_count,
    COUNT(i.id) as invoice_count,
    v.created_at,
    v.updated_at
FROM visits v
LEFT JOIN patients p ON v.patient_id = p.id
LEFT JOIN patient_demographics pd ON p.id = pd.patient_id
LEFT JOIN facilities f ON v.facility_id = f.id
LEFT JOIN encounters e ON v.id = e.visit_id
LEFT JOIN medication_requests mr ON v.id = mr.visit_id
LEFT JOIN service_requests sr ON v.id = sr.visit_id
LEFT JOIN invoices i ON v.id = i.visit_id
GROUP BY v.id, p.code, pd.given_name, pd.family_name, f.name;

-- Create function for patient search
CREATE OR REPLACE FUNCTION search_patients(search_term TEXT)
RETURNS TABLE (
    patient_id INTEGER,
    patient_code VARCHAR,
    full_name TEXT,
    phone_number VARCHAR,
    email VARCHAR,
    rank REAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        p.id,
        p.code,
        COALESCE(pd.given_name, '') || ' ' || COALESCE(pd.family_name, '') as full_name,
        pd.phone_number,
        pd.email,
        ts_rank(
            to_tsvector('english', COALESCE(pd.given_name, '') || ' ' || COALESCE(pd.family_name, '') || ' ' || COALESCE(p.code, '') || ' ' || COALESCE(pd.phone_number, '') || ' ' || COALESCE(pd.email, '')),
            plainto_tsquery('english', search_term)
        ) as rank
    FROM patients p
    LEFT JOIN patient_demographics pd ON p.id = pd.patient_id
    WHERE 
        to_tsvector('english', COALESCE(pd.given_name, '') || ' ' || COALESCE(pd.family_name, '') || ' ' || COALESCE(p.code, '') || ' ' || COALESCE(pd.phone_number, '') || ' ' || COALESCE(pd.email, '')) 
        @@ plainto_tsquery('english', search_term)
        OR p.code ILIKE '%' || search_term || '%'
        OR pd.phone_number ILIKE '%' || search_term || '%'
        OR pd.email ILIKE '%' || search_term || '%'
    ORDER BY rank DESC, p.created_at DESC;
END;
$$ LANGUAGE plpgsql;

-- Create function for active insurance lookup
CREATE OR REPLACE FUNCTION get_active_insurance(patient_id_param INTEGER)
RETURNS TABLE (
    identity_code VARCHAR,
    card_type VARCHAR,
    is_beneficiary BOOLEAN,
    start_date DATE,
    end_date DATE
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        pi.identity_code,
        c.card_type,
        pi.is_beneficiary,
        pi.start_date,
        pi.end_date
    FROM patient_identities pi
    JOIN cards c ON pi.card_id = c.id
    WHERE pi.patient_id = patient_id_param
        AND pi.start_date <= CURRENT_DATE
        AND (pi.end_date IS NULL OR pi.end_date >= CURRENT_DATE)
    ORDER BY pi.start_date DESC
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- Create stored procedure for visit export
CREATE OR REPLACE FUNCTION export_visit_data(visit_id_param INTEGER)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'visit', json_build_object(
            'id', v.id,
            'patient_id', v.patient_id,
            'facility_id', v.facility_id,
            'visit_type_id', v.visit_type_id,
            'admission_type_id', v.admission_type_id,
            'admitted_at', v.admitted_at,
            'discharged_at', v.discharged_at,
            'discharge_type_id', v.discharge_type_id,
            'visit_outcome_id', v.visit_outcome_id,
            'patient', json_build_object(
                'id', p.id,
                'code', p.code,
                'demographics', json_build_object(
                    'given_name', pd.given_name,
                    'family_name', pd.family_name,
                    'date_of_birth', pd.date_of_birth,
                    'gender', pd.gender,
                    'phone_number', pd.phone_number,
                    'email', pd.email
                ),
                'addresses', (
                    SELECT json_agg(json_build_object(
                        'id', pa.id,
                        'address_line_1', pa.address_line_1,
                        'postal_code', pa.postal_code,
                        'province', g_province.name,
                        'district', g_district.name,
                        'commune', g_commune.name,
                        'village', g_village.name,
                        'is_primary', pa.is_primary
                    ))
                    FROM patient_addresses pa
                    LEFT JOIN gazetteers g_province ON pa.province_id = g_province.id
                    LEFT JOIN gazetteers g_district ON pa.district_id = g_district.id
                    LEFT JOIN gazetteers g_commune ON pa.commune_id = g_commune.id
                    LEFT JOIN gazetteers g_village ON pa.village_id = g_village.id
                    WHERE pa.patient_id = p.id
                ),
                'identifications', (
                    SELECT json_agg(json_build_object(
                        'id', pi.id,
                        'identity_code', pi.identity_code,
                        'card_type', c.card_type,
                        'is_beneficiary', pi.is_beneficiary,
                        'start_date', pi.start_date,
                        'end_date', pi.end_date
                    ))
                    FROM patient_identities pi
                    JOIN cards c ON pi.card_id = c.id
                    WHERE pi.patient_id = p.id
                )
            ),
            'encounters', (
                SELECT json_agg(json_build_object(
                    'id', e.id,
                    'encounter_type_id', e.encounter_type_id,
                    'department_id', e.department_id,
                    'room_id', e.room_id,
                    'started_at', e.started_at,
                    'ended_at', e.ended_at,
                    'observations', (
                        SELECT json_agg(json_build_object(
                            'id', o.id,
                            'observation_concept_id', o.observation_concept_id,
                            'value_string', o.value_string,
                            'value_number', o.value_number,
                            'value_datetime', o.value_datetime,
                            'unit', o.unit,
                            'recorded_at', o.recorded_at
                        ))
                        FROM observations o
                        WHERE o.encounter_id = e.id
                    )
                ))
                FROM encounters e
                WHERE e.visit_id = v.id
            ),
            'medications', (
                SELECT json_agg(json_build_object(
                    'id', mr.id,
                    'medication_concept_id', mr.medication_concept_id,
                    'status_id', mr.status_id,
                    'intent_id', mr.intent_id,
                    'priority', mr.priority,
                    'requested_at', mr.requested_at,
                    'instructions', json_build_object(
                        'morning_dose', mi.morning_dose,
                        'afternoon_dose', mi.afternoon_dose,
                        'evening_dose', mi.evening_dose,
                        'night_dose', mi.night_dose,
                        'days', mi.days,
                        'special_instructions', mi.special_instructions
                    ),
                    'dispenses', (
                        SELECT json_agg(json_build_object(
                            'id', md.id,
                            'quantity_dispensed', md.quantity_dispensed,
                            'dispensed_at', md.dispensed_at,
                            'dispenser_notes', md.dispenser_notes
                        ))
                        FROM medication_dispenses md
                        WHERE md.medication_request_id = mr.id
                    ),
                    'administrations', (
                        SELECT json_agg(json_build_object(
                            'id', ma.id,
                            'administered_dose', ma.administered_dose,
                            'administration_route', ma.administration_route,
                            'administered_at', ma.administered_at,
                            'administrator_notes', ma.administrator_notes
                        ))
                        FROM medication_administrations ma
                        WHERE ma.medication_request_id = mr.id
                    )
                ))
                FROM medication_requests mr
                LEFT JOIN medication_instructions mi ON mr.id = mi.medication_request_id
                WHERE mr.visit_id = v.id
            ),
            'service_requests', (
                SELECT json_agg(json_build_object(
                    'id', sr.id,
                    'request_type', sr.request_type,
                    'service_concept_id', sr.service_concept_id,
                    'status_id', sr.status_id,
                    'priority', sr.priority,
                    'requested_at', sr.requested_at,
                    'completed_at', sr.completed_at,
                    'clinical_notes', sr.clinical_notes,
                    'results', (
                        SELECT json_agg(json_build_object(
                            'id', o.id,
                            'observation_concept_id', o.observation_concept_id,
                            'value_string', o.value_string,
                            'value_number', o.value_number,
                            'value_datetime', o.value_datetime,
                            'unit', o.unit,
                            'reference_range', o.reference_range,
                            'recorded_at', o.recorded_at
                        ))
                        FROM observations o
                        WHERE o.service_request_id = sr.id
                    )
                ))
                FROM service_requests sr
                WHERE sr.visit_id = v.id
            ),
            'invoices', (
                SELECT json_agg(json_build_object(
                    'id', i.id,
                    'invoice_number', i.invoice_number,
                    'status_id', i.status_id,
                    'payment_type_id', i.payment_type_id,
                    'total_amount', i.total_amount,
                    'discount_percentage', i.discount_percentage,
                    'discount_amount', i.discount_amount,
                    'final_amount', i.final_amount,
                    'invoice_date', i.invoice_date,
                    'due_date', i.due_date,
                    'items', (
                        SELECT json_agg(json_build_object(
                            'id', ii.id,
                            'service_id', ii.service_id,
                            'description', ii.description,
                            'quantity', ii.quantity,
                            'unit_price', ii.unit_price,
                            'total_price', ii.total_price
                        ))
                        FROM invoice_items ii
                        WHERE ii.invoice_id = i.id
                    ),
                    'payments', (
                        SELECT json_agg(json_build_object(
                            'id', ip.id,
                            'amount', ip.amount,
                            'payment_method', ip.payment_method,
                            'payment_reference', ip.payment_reference,
                            'payment_date', ip.payment_date,
                            'payment_notes', ip.payment_notes
                        ))
                        FROM invoice_payments ip
                        WHERE ip.invoice_id = i.id
                    )
                ))
                FROM invoices i
                WHERE i.visit_id = v.id
            )
        )
    ) INTO result
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN patient_demographics pd ON p.id = pd.patient_id
    WHERE v.id = visit_id_param;
    
    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Create maintenance procedures
CREATE OR REPLACE FUNCTION cleanup_expired_tokens()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    DELETE FROM personal_access_tokens 
    WHERE expires_at IS NOT NULL AND expires_at < NOW();
    
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    
    DELETE FROM oauth_access_tokens 
    WHERE expires_at < NOW();
    
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Create backup and maintenance schedule comments
COMMENT ON DATABASE current_database() IS 'EMR FHIR System Database - Requires daily backups and weekly maintenance';

-- Performance monitoring queries (for DBA reference)
/*
-- Monitor slow queries
SELECT query, mean_time, calls, total_time
FROM pg_stat_statements
WHERE mean_time > 1000
ORDER BY mean_time DESC;

-- Monitor table sizes
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Monitor index usage
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes
ORDER BY idx_tup_read DESC;
*/