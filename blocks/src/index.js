import { registerBlockType } from '@wordpress/blocks';
import { useEffect, useState } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { SelectControl, Spinner, TextControl, Button, PanelBody, CheckboxControl, RadioControl, BaseControl, ColorPicker } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './style.css';

registerBlockType('gan/newsletter-form', {
    title: __('Newsletter Form', 'getanewsletter'),
    icon: 'email',
    category: 'widgets',
    attributes: {
        formId: {
            type: 'string',
            default: '',
        },
        isTitleEnabled: {
            type: 'boolean',
            default: false,
        },
        formTitle: {
            type: 'string',
            default: 'Join our newsletter',
        },
        isDescriptionEnabled: {
            type: 'boolean',
            default: false,
        },
        formDescription: {
            type: 'string',
            default: 'Get weekly access to our deals, tricks and tips',
        },
        appearance: {
            type: 'string',
            default: 'square',
        },
        fieldBackground: {
            type: 'string',
            default: '#ffffff',
        },
        fieldBorder: {
            type: 'string',
            default: '#000000',
        },
        labelColor: {
            type: 'string',
            default: '#000000',
        },
        buttonBackground: {
            type: 'string',
            default: '#0280FF',
        },
        buttonTextColor: {
            type: 'string',
            default: '#000000',
        },
        errorMessage: {
            'type': 'string',
            'default': ''
        }
    },
    edit: ({ attributes, setAttributes }) => {
        const [forms, setForms] = useState([]);
        const [formData, setFormData] = useState(null);
        const [isLoadingForms, setIsLoadingForms] = useState(true);
        const [isLoadingFormData, setIsLoadingFormData] = useState(false);

        useEffect(() => {
            setIsLoadingForms(true);
            fetch(`${ganAjax.ajaxurl}?action=gan_get_subscription_forms_list`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setForms(data.data);
                    } else {
                        setAttributes({ errorMessage: data.data });
                    }
                    setIsLoadingForms(false);
                });
        }, []);

        useEffect(() => {
            if (attributes.formId) {
                setIsLoadingFormData(true);
                fetch(`${ganAjax.ajaxurl}?action=gan_get_subscription_form`, {
                    method: 'POST',
                    body: new URLSearchParams({ form_id: attributes.formId }),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            setFormData(data.data);
                        } else {
                            setAttributes({ errorMessage: data.data });
                        }
                        setIsLoadingFormData(false);
                    });
            }
        }, [attributes.formId]);

        useEffect(() => {
            const blockElement = document.querySelector('.gan-newsletter-form');
            if (blockElement) {
                blockElement.style.setProperty('--border-radius', attributes.appearance === 'rounded' ? '8px' : '0');
                blockElement.style.setProperty('--field-background', attributes.fieldBackground);
                blockElement.style.setProperty('--field-border', attributes.fieldBorder);
                blockElement.style.setProperty('--label-color', attributes.labelColor);
                blockElement.style.setProperty('--button-background', attributes.buttonBackground);
                blockElement.style.setProperty('--button-text-color', attributes.buttonTextColor);
            }
        }, [attributes.appearance, attributes.fieldBackground, attributes.fieldBorder, attributes.labelColor, attributes.buttonBackground, attributes.buttonTextColor]);

        const handleFormChange = (formId) => {
            setAttributes({ formId });
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Form Settings', 'getanewsletter')}>
                        {isLoadingForms ? (
                            <Spinner />
                        ) : (
                            <SelectControl
                                label={__('Select Form', 'getanewsletter')}
                                value={attributes.formId}
                                options={forms.map(form => ({
                                    label: form.name,
                                    value: form.key,
                                }))}
                                onChange={handleFormChange}
                            />
                        )}
                        <CheckboxControl
                            label={__('Title', 'getanewsletter')}
                            checked={attributes.isTitleEnabled}
                            onChange={(isTitleEnabled) => setAttributes({ isTitleEnabled })}
                        />
                        {attributes.isTitleEnabled && (
                            <TextControl
                                label={__('Title', 'getanewsletter')}
                                value={attributes.formTitle}
                                onChange={(formTitle) => setAttributes({ formTitle })}
                                placeholder="Join our newsletter"
                            />
                        )}
                        <CheckboxControl
                            label={__('Description', 'getanewsletter')}
                            checked={attributes.isDescriptionEnabled}
                            onChange={(isDescriptionEnabled) => setAttributes({ isDescriptionEnabled })}
                        />
                        {attributes.isDescriptionEnabled && (
                            <TextControl
                                label={__('Description', 'getanewsletter')}
                                value={attributes.formDescription}
                                onChange={(formDescription) => setAttributes({ formDescription })}
                                placeholder="Get weekly access to our deals, tricks and tips"
                            />
                        )}
                        <RadioControl
                            label={__('Appearance', 'getanewsletter')}
                            selected={attributes.appearance}
                            options={[
                                { label: __('Square', 'getanewsletter'), value: 'square' },
                                { label: __('Rounded', 'getanewsletter'), value: 'rounded' },
                            ]}
                            onChange={(appearance) => setAttributes({ appearance })}
                        />
                        <BaseControl label={__('Field Background', 'getanewsletter')}>
                            <ColorPicker
                                color={attributes.fieldBackground}
                                onChangeComplete={(color) => setAttributes({ fieldBackground: color.hex })}
                            />
                        </BaseControl>
                        <BaseControl label={__('Field Border', 'getanewsletter')}>
                            <ColorPicker
                                color={attributes.fieldBorder}
                                onChangeComplete={(color) => setAttributes({ fieldBorder: color.hex })}
                            />
                        </BaseControl>
                        <BaseControl label={__('Label Color', 'getanewsletter')}>
                            <ColorPicker
                                color={attributes.labelColor}
                                onChangeComplete={(color) => setAttributes({ labelColor: color.hex })}
                            />
                        </BaseControl>
                        <BaseControl label={__('Button Background', 'getanewsletter')}>
                            <ColorPicker
                                color={attributes.buttonBackground}
                                onChangeComplete={(color) => setAttributes({ buttonBackground: color.hex })}
                            />
                        </BaseControl>
                        <BaseControl label={__('Button Text Color', 'getanewsletter')}>
                            <ColorPicker
                                color={attributes.buttonTextColor}
                                onChangeComplete={(color) => setAttributes({ buttonTextColor: color.hex })}
                            />
                        </BaseControl>
                    </PanelBody>
                </InspectorControls>
                <div className="gan-newsletter-form">
                    {attributes.errorMessage && (
                        <div className="error-message">
                            {attributes.errorMessage}
                        </div>
                    )}
                    {!attributes.formId && (
                        <>
                            {isLoadingForms ? (
                                <Spinner />
                            ) : (
                                <SelectControl
                                    label={__('Select Form', 'getanewsletter')}
                                    value={attributes.formId}
                                    options={forms.map(form => ({
                                        label: form.name,
                                        value: form.key,
                                    }))}
                                    onChange={handleFormChange}
                                />
                            )}
                        </>
                    )}
                    {attributes.isTitleEnabled && (
                        <h2>{attributes.formTitle}</h2>
                    )}
                    {attributes.isDescriptionEnabled && (
                        <p>{attributes.formDescription}</p>
                    )}
                    {isLoadingFormData ? (
                        <Spinner />
                    ) : (
                        formData && (
                            <form method="post" className="newsletter-signup" action="javascript:alert('success!');" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="getanewsletter_subscribe" />
                                {formData.form.first_name && (
                                    <p>
                                        <label htmlFor="id_first_name">{formData.form.first_name_label || __('First name', 'getanewsletter')}</label><br />
                                        <input id="id_first_name" type="text" className="text" name="id_first_name" />
                                    </p>
                                )}
                                {formData.form.last_name && (
                                    <p>
                                        <label htmlFor="id_last_name">{formData.form.last_name_label || __('Last name', 'getanewsletter')}</label><br />
                                        <input id="id_last_name" type="text" className="text" name="id_last_name" />
                                    </p>
                                )}
                                <p>
                                    <label htmlFor="id_email">{__('E-mail', 'getanewsletter')}</label><br />
                                    <input id="id_email" type="text" className="text" name="id_email" />
                                </p>
                                {formData.customAttributes.map(attribute => (
                                    formData.form.attributes.includes(attribute.code) && (
                                        <p key={attribute.code}>
                                            <label htmlFor={`attr_${attribute.code}`}>{attribute.name}</label><br />
                                            <input id={`attr_${attribute.code}`} type="text" className="text" name={`attributes[${attribute.code}]`} />
                                        </p>
                                    )
                                ))}
                                <p>
                                    <input type="hidden" name="form_link" value={formData.form.form_link} id="id_form_link" />
                                    <input type="hidden" name="key" value={formData.form.key} id="id_key" />
                                    <Button type="submit">{formData.form.button_text || __('Subscribe', 'getanewsletter')}</Button>
                                </p>
                            </form>
                        )
                    )}
                    <div className="news-note"></div>
                </div>
            </>
        );
    },
    save: () => null,
});
